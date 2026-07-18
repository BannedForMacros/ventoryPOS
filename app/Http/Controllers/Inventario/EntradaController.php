<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Cuenta;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Stock;
use App\Services\AuditoriaService;
use App\Services\LocalScopeService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EntradaController extends Controller
{
    public function __construct(
        private LocalScopeService $scope,
        private TesoreriaService $tesoreria,
    ) {}

    /**
     * Registra los pagos iniciales de una entrada (líneas método+cuenta+monto):
     * crea los entrada_pagos, asienta cada egreso en tesorería y sincroniza
     * monto_pagado/estado_pago.
     *
     * La fecha del pago es la que trae CADA línea (`fecha`, del formulario — mismo
     * criterio que Cuentas por Pagar); si la línea no la trae, se usa HOY (cuando
     * sale el dinero), NUNCA la fecha de la compra (eso causaba los "pagos
     * retrofechados" que movían el efectivo a días ya cerrados). Así el pago desde
     * la Entrada y desde Cuentas por Pagar quedan sincronizados.
     *
     * @param array<array{metodo_pago_id:int|null,cuenta_id:int|null,monto:float|string,referencia?:string|null,fecha?:string|null}> $lineas
     */
    private function registrarPagosIniciales(Entrada $entrada, array $lineas, int $userId, int|string|null $turnoId = 'auto'): void
    {
        $prov = $entrada->proveedor ?? 'proveedor';

        // Turno al que se imputa el pago (si es efectivo, SALE de esa caja).
        // 'auto' = resolver automáticamente (turno propio del usuario, o el único
        // abierto). Un int explícito o null lo fija el llamador (selector "Afecta
        // caja a:") — null = el pago NO afecta ninguna caja.
        if ($turnoId === 'auto') {
            $turnoId = \App\Models\Turno::turnoActivoDelUsuario($userId)?->id;
            if (!$turnoId) {
                $abiertos = \App\Models\Turno::deEmpresa($entrada->empresa_id)->where('estado', 'abierto')->pluck('id');
                $turnoId = $abiertos->count() === 1 ? $abiertos->first() : null;
            }
        }

        foreach ($lineas as $linea) {
            $pago = EntradaPago::create([
                'entrada_id'     => $entrada->id,
                'user_id'        => $userId,
                'turno_id'       => $turnoId,
                'metodo_pago_id' => $linea['metodo_pago_id'] ?? null,
                'cuenta_id'      => $linea['cuenta_id'] ?? null,
                'fecha'          => !empty($linea['fecha'])          // fecha del formulario (como CxP);
                    ? substr((string) $linea['fecha'], 0, 10)         // si no viene, HOY — nunca la de la compra
                    : now()->toDateString(),
                'monto'          => round((float) $linea['monto'], 2),
                'referencia'     => $linea['referencia'] ?? null,
            ]);

            $this->tesoreria->registrar(
                $entrada->empresa_id,
                $linea['cuenta_id'] ?? $this->tesoreria->resolverCuenta($entrada->empresa_id, null, $linea['metodo_pago_id'] ?? null),
                $userId,
                (string) $pago->fecha,
                'egreso',
                (float) $pago->monto,
                "Pago a proveedor {$prov}" . ($entrada->numero_documento ? " ({$entrada->numero_documento})" : ''),
                'entrada_pago',
                $pago->id,
            );

            $entrada->aplicarPago((float) $pago->monto);
        }
    }

    /**
     * Asigna (una sola vez) el correlativo interno E-AAAAMMDD-NNN a la entrada.
     *
     * Debe llamarse DENTRO de la transacción de store()/update() y DESPUÉS de que
     * la entrada tenga su `fecha` definitiva. Envuelve cada intento en una
     * transacción anidada (SAVEPOINT en PostgreSQL): si otro request ganó el mismo
     * número, el UNIQUE parcial dispara UniqueConstraintViolationException, el
     * savepoint hace rollback (sin abortar la transacción externa) y se reintenta
     * con el siguiente número. Patrón optimistic-insert + retry, como en ventas.
     */
    private function asignarCorrelativo(Entrada $entrada, int $empresaId, string|\Carbon\CarbonInterface $fecha): void
    {
        $fechaStr = $fecha instanceof \Carbon\CarbonInterface
            ? $fecha->toDateString()
            : substr((string) $fecha, 0, 10);

        for ($intento = 1; $intento <= 5; $intento++) {
            try {
                DB::transaction(function () use ($entrada, $empresaId, $fechaStr) {
                    $entrada->update(['correlativo' => Entrada::generarCorrelativo($empresaId, $fechaStr)]);
                });
                return;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($intento >= 5) {
                    throw $e; // no debería pasar bajo carga normal
                }
                // colisión con el índice único: recalcula en el siguiente intento
                // (MAX verá ahora el número recién insertado por el ganador).
            }
        }
    }

    public function index(Request $request)
    {
        $user       = $request->user();
        // El listado muestra entradas de TODOS los almacenes visibles para histórico,
        // aunque la creación esté restringida a almacenes para compras.
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        // M19: paginar (default 25/page) y preservar query string en navegación.
        $entradas = Entrada::whereIn('almacen_id', $almacenIds)
            ->with(['almacen.local', 'user', 'proveedorRel', 'metodoPago', 'cuenta'])
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            // Búsqueda SERVER-SIDE sobre TODA la base (no solo la página visible):
            // documento, proveedor de texto libre y proveedor del catálogo. Antes se
            // filtraba en el cliente sobre las 25 filas de la página, así que una
            // entrada en otra página nunca aparecía al buscar (bug reportado).
            ->when($request->buscar, function ($q, $texto) {
                $t = trim($texto);
                $q->where(function ($sub) use ($t) {
                    $sub->where('numero_documento', 'ilike', "%{$t}%")
                        ->orWhere('proveedor', 'ilike', "%{$t}%")
                        ->orWhereHas('proveedorRel', fn ($p) => $p
                            ->where('razon_social', 'ilike', "%{$t}%")
                            ->orWhere('nombre_comercial', 'ilike', "%{$t}%"));
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventario/Entradas/Index', [
            'entradas'        => $entradas,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'filters'         => $request->only(['almacen_id', 'estado', 'fecha_desde', 'fecha_hasta', 'buscar']),
            // Para el modal de quick-pago en el Index. Eager cuentas para evitar query
            // adicional al abrir el modal y permitir filtrar cuentas por metodo en cliente.
            'metodosPago'     => MetodoPago::deEmpresa($user->empresa_id)->activo()
                ->with('cuentas:id,nombre,banco,numero_cuenta')
                ->orderBy('nombre')->get(['id', 'nombre', 'tipo_id']),
        ]);
    }

    public function create(Request $request)
    {
        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Entradas/Create', [
            'almacenes' => $this->scope->almacenesParaCompras($user),
            'modoAlmacen' => $user->empresa->modo_almacen,
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                // Solo unidades ACTIVAS y con la base primero: una presentación
                // desactivada no debe aparecer ni preseleccionarse como default.
                ->with([
                    'unidades' => fn ($q) => $q->where('activo', true)
                        ->orderByDesc('es_base')
                        ->with('unidadMedida'),
                ])
                ->orderBy('nombre')
                ->get(),
            'proveedores' => Proveedor::deEmpresa($empresaId)
                ->activo()
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial', 'numero_documento', 'tipo_documento']),
            'metodosPago' => MetodoPago::deEmpresa($empresaId)->activo()
                ->with('cuentas:id,nombre,banco,numero_cuenta')
                ->orderBy('nombre')->get(['id', 'nombre', 'tipo_id']),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            // "Afecta caja a:" — turnos para elegir de qué caja sale el pago, y el
            // turno activo del usuario como default (su propia caja).
            'turnos' => \App\Models\Turno::deEmpresa($empresaId)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
            'turnoActivoId' => \App\Models\Turno::turnoActivoDelUsuario($user->id)?->id,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'proveedor_id'     => 'nullable|exists:proveedores,id',
            'proveedor'        => 'nullable|string|max:150',
            'numero_documento' => 'nullable|string|max:50',
            'tipo'             => 'required|in:compra,ajuste,devolucion,otro',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.precio_costo'      => 'required|numeric|min:0',
            // NULL = hereda numero_documento de la cabecera al mostrar; no se copia el valor
            // para que cambiar la cabecera actualice los items que no tienen factura propia.
            'detalles.*.numero_documento'  => 'nullable|string|max:50',
            // Pago: independiente del estado de la entrada (puede estar confirmada y pendiente
            // de pago). Acepta pago total, PARCIAL y múltiples métodos: `pagos` es un
            // array de líneas {metodo, cuenta opcional, monto}. El saldo queda como CxP.
            'estado_pago'      => 'nullable|in:pendiente,parcial,pagado',
            'metodo_pago_id'   => 'nullable|exists:metodos_pago,id',
            'cuenta_id'        => 'nullable|exists:cuentas,id',
            'pagos'                    => 'nullable|array|max:10',
            'pagos.*.metodo_pago_id'   => ['required', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos.*.cuenta_id'        => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos.*.monto'            => 'required|numeric|min:0.01',
            'pagos.*.referencia'       => 'nullable|string|max:200',
            'pagos.*.fecha'            => ['nullable', 'date'],
            // "Afecta caja a:" — turno de cuya caja sale el efectivo del pago.
            'turno_id'                 => ['nullable', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        $almacen = Almacen::find($data['almacen_id']);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        // Si vino proveedor_id, validar que sea de la empresa y completar el campo proveedor (denormalizado)
        if (!empty($data['proveedor_id'])) {
            $prov = Proveedor::where('id', $data['proveedor_id'])
                ->where('empresa_id', $user->empresa_id)
                ->firstOrFail();
            $data['proveedor'] = $prov->razon_social ?: $prov->nombre_comercial;
        }

        // En modo central_y_local las compras solo pueden ingresar al almacén central
        if ($user->empresa->usaCentralYLocal() && !$almacen->esCentral()) {
            return back()->withErrors([
                'almacen_id' => 'Las entradas (compras) solo pueden ingresar al almacén central. Para mover stock a un local, usa Transferencias.',
            ])->withInput();
        }

        DB::transaction(function () use ($data, $user) {
            $total = 0;

            $estadoPago = $data['estado_pago'] ?? 'pendiente';
            $entrada = Entrada::create([
                'empresa_id'       => $user->empresa_id,
                'almacen_id'       => $data['almacen_id'],
                'user_id'          => $user->id,
                'proveedor_id'     => $data['proveedor_id'] ?? null,
                'proveedor'        => $data['proveedor'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'tipo'             => $data['tipo'],
                'fecha'            => $data['fecha'],
                'observacion'      => $data['observacion'] ?? null,
                'estado'           => 'borrador',
                'total'            => 0,
                'estado_pago'      => 'pendiente', // lo sincroniza registrarPagosIniciales()
                // Legacy: metodo/cuenta de cabecera = primera línea de pago (solo display).
                'metodo_pago_id'   => $data['pagos'][0]['metodo_pago_id'] ?? ($estadoPago === 'pagado' ? ($data['metodo_pago_id'] ?? null) : null),
                'cuenta_id'        => $data['pagos'][0]['cuenta_id'] ?? ($estadoPago === 'pagado' ? ($data['cuenta_id'] ?? null) : null),
            ]);

            // Correlativo interno E-AAAAMMDD-NNN (por empresa+día de la fecha).
            // Inmutable una vez asignado: si luego se edita la fecha NO se regenera.
            $this->asignarCorrelativo($entrada, $user->empresa_id, $data['fecha']);

            foreach ($data['detalles'] as $d) {
                $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);
                $subtotal     = round((float) $d['cantidad'] * (float) $d['precio_costo'], 2);
                $total       += $subtotal;

                $entrada->detalles()->create([
                    'producto_id'      => $d['producto_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad'         => $d['cantidad'],
                    'factor_conversion'=> $d['factor_conversion'],
                    'cantidad_base'    => $cantidadBase,
                    'precio_costo'     => $d['precio_costo'],
                    'subtotal'         => $subtotal,
                    'numero_documento' => $d['numero_documento'] ?? null,
                ]);
            }

            $entrada->update(['total' => $total]);

            // ── Pagos iniciales (total, parcial o múltiples métodos) ────────
            $lineas = $data['pagos'] ?? [];

            // Legacy: form viejo con "pagado" + un solo método sin array de pagos.
            if (empty($lineas) && $estadoPago === 'pagado' && !empty($data['metodo_pago_id'])) {
                $lineas = [[
                    'metodo_pago_id' => $data['metodo_pago_id'],
                    'cuenta_id'      => $data['cuenta_id'] ?? null,
                    'monto'          => $total,
                ]];
            }

            if ($estadoPago === 'pendiente') {
                $lineas = []; // defensivo: pendiente = sin pagos aunque el form los mande
            }

            if (!empty($lineas)) {
                $suma = round(array_sum(array_map(fn ($l) => (float) $l['monto'], $lineas)), 2);

                if ($suma > $total + 0.01) {
                    throw ValidationException::withMessages([
                        'pagos' => "Los pagos (S/ {$suma}) superan el total de la compra (S/ " . round($total, 2) . ').',
                    ]);
                }
                if ($estadoPago === 'pagado' && abs($suma - $total) > 0.01) {
                    throw ValidationException::withMessages([
                        'pagos' => "Marcaste la compra como pagada, pero los pagos suman S/ {$suma} y el total es S/ " . round($total, 2) . '. Usa "Pago parcial" o completa el monto.',
                    ]);
                }

                // "Afecta caja a:" — si el form envió turno_id se respeta (incluye
                // null = no afecta caja); si no lo envió, 'auto' resuelve el turno.
                $turnoArg = array_key_exists('turno_id', $data) ? ($data['turno_id'] ?: null) : 'auto';
                $this->registrarPagosIniciales($entrada, $lineas, $user->id, $turnoArg);
            }

            // Si se pidió confirmar directamente
            if (request()->boolean('confirmar')) {
                $entrada->confirmar();
            }
        });

        return redirect()->route('inventario.entradas.index')
            ->with('success', 'Entrada registrada correctamente.');
    }

    public function edit(Request $request, Entrada $entrada)
    {
        // M23: ahora tambien se pueden editar entradas confirmadas — los usuarios se
        // equivocan (cantidad mal tipeada, precio con cero de mas, producto incorrecto)
        // y necesitan corregirlas. update() recompone stock y CPP automaticamente.
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        // Stock actual del almacen de la entrada — necesario en el form si la entrada
        // esta confirmada para calcular las restricciones de reduccion (no se puede
        // bajar cantidad de un producto que ya tuvo salidas).
        $stocks = $entrada->estado === 'confirmado'
            ? \App\Models\Stock::where('almacen_id', $entrada->almacen_id)
                ->get(['almacen_id', 'producto_id', 'cantidad'])
            : collect();

        // Turnos para el selector "Afecta caja a:" — los del día de la entrada, los
        // abiertos ahora, y SIEMPRE el que ya tiene asignado el pago (aunque sea de
        // otro día/cerrado), para que el selector muestre el valor actual.
        $fechaEntrada = $entrada->fecha instanceof \Carbon\CarbonInterface
            ? $entrada->fecha->toDateString() : substr((string) $entrada->fecha, 0, 10);
        $pagoTurnoId = $entrada->pagosParciales()->value('turno_id');
        $turnos = \App\Models\Turno::deEmpresa($empresaId)
            ->with(['user:id,name', 'caja:id,nombre'])
            ->where(function ($q) use ($fechaEntrada, $pagoTurnoId) {
                $q->whereDate('fecha_apertura', $fechaEntrada)->orWhere('estado', 'abierto');
                if ($pagoTurnoId) $q->orWhere('id', $pagoTurnoId);
            })
            ->orderByDesc('fecha_apertura')->limit(40)
            ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']);

        // Pagos YA registrados de esta entrada — para mostrarle al usuario con qué
        // método(s) pagó antes (no solo el total). Un admin puede editarlos/anularlos
        // inline (reusa los endpoints cxp.pagos.*); si no, se muestran read-only.
        $pagosPrevios = $entrada->pagosParciales()
            ->with(['metodoPago:id,nombre', 'cuenta:id,nombre,banco'])
            ->orderBy('fecha')->orderBy('id')
            ->get(['id', 'metodo_pago_id', 'cuenta_id', 'turno_id', 'monto', 'fecha', 'referencia', 'proveedor_adelanto_id']);

        return Inertia::render('Inventario/Entradas/Edit', [
            'entrada'   => $entrada->load(['detalles.producto', 'detalles.unidadMedida', 'proveedorRel', 'metodoPago', 'cuenta']),
            'pagosPrevios' => $pagosPrevios,
            // Editar/anular pagos ya registrados mueve tesorería → solo admin (igual
            // que CuentasPorPagarController::editarPago/eliminarPago).
            'puedeEditarPagos' => (bool) $user->rol->es_admin,
            'turnos'      => $turnos,
            'pagoTurnoId' => $pagoTurnoId,
            'almacenes' => $this->scope->almacenesParaCompras($user),
            'modoAlmacen' => $user->empresa->modo_almacen,
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
            'proveedores' => Proveedor::deEmpresa($empresaId)
                ->activo()
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'nombre_comercial', 'numero_documento', 'tipo_documento']),
            'metodosPago' => MetodoPago::deEmpresa($empresaId)->activo()
                ->with('cuentas:id,nombre,banco,numero_cuenta')
                ->orderBy('nombre')->get(['id', 'nombre', 'tipo_id']),
            'stocks'    => $stocks,
            // Productos de esta entrada ABSORBIDOS por el inventario inicial (la
            // fecha de la entrada cae en o antes del corte de apertura): su
            // mercadería vive en el conteo físico, no en el stock en vivo, así
            // que el form NO debe restringir su reducción (edición documental).
            'productosAbsorbidos' => $entrada->estado === 'confirmado'
                ? $entrada->detalles->pluck('producto_id')->unique()
                    ->filter(fn ($pid) => \App\Models\Stock::absorbidoPorApertura($entrada->almacen_id, (int) $pid, $entrada->fecha))
                    ->values()
                : collect(),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function update(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $user = $request->user();

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'proveedor_id'     => 'nullable|exists:proveedores,id',
            'proveedor'        => 'nullable|string|max:150',
            'numero_documento' => 'nullable|string|max:50',
            'tipo'             => 'required|in:compra,ajuste,devolucion,otro',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.precio_costo'      => 'required|numeric|min:0',
            // NULL = hereda numero_documento de la cabecera al mostrar; no se copia el valor
            // para que cambiar la cabecera actualice los items que no tienen factura propia.
            'detalles.*.numero_documento'  => 'nullable|string|max:50',
            // Pagos NUEVOS registrados desde la edición (p. ej. se agregó un
            // producto y se paga la diferencia ahí mismo).
            'pagos'                    => 'nullable|array|max:10',
            'pagos.*.metodo_pago_id'   => ['required', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos.*.cuenta_id'        => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos.*.monto'            => 'required|numeric|min:0.01',
            'pagos.*.referencia'       => 'nullable|string|max:200',
            'pagos.*.fecha'            => ['nullable', 'date'],
            // Pagos YA registrados EDITADOS en la misma pantalla (solo admin). Se
            // guardan junto con todo lo demás en un solo submit — sin botón por fila.
            'pagos_editados'                  => 'nullable|array',
            'pagos_editados.*.id'             => ['required', 'integer'],
            'pagos_editados.*.monto'          => ['nullable', 'numeric', 'min:0.01'],
            'pagos_editados.*.metodo_pago_id' => ['nullable', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos_editados.*.cuenta_id'      => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'pagos_editados.*.turno_id'       => ['nullable', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
            // Pagos YA registrados ANULADOS en la misma pantalla (solo admin): ids.
            'pagos_anulados'                  => 'nullable|array',
            'pagos_anulados.*'                => ['integer'],
            // "Afecta caja a:" — turno cuya caja entregó el efectivo del pago NUEVO.
            'turno_id'                 => ['nullable', Rule::exists('turnos', 'id')->where('empresa_id', $user->empresa_id)],
            // Si el usuario TOCÓ el selector "Afecta caja a:", re-imputar también
            // los pagos YA registrados a ese turno (no solo los nuevos).
            'reimputar_pagos_turno'    => ['nullable', 'boolean'],
        ]);

        $almacen = Almacen::find($data['almacen_id']);
        if ($user->empresa->usaCentralYLocal() && !$almacen->esCentral()) {
            return back()->withErrors([
                'almacen_id' => 'Las entradas solo pueden ingresar al almacén central.',
            ])->withInput();
        }

        if (!empty($data['proveedor_id'])) {
            $prov = Proveedor::where('id', $data['proveedor_id'])
                ->where('empresa_id', $user->empresa_id)
                ->firstOrFail();
            $data['proveedor'] = $prov->razon_social ?: $prov->nombre_comercial;
        }

        $eraConfirmada    = $entrada->estado === 'confirmado';
        $almacenAnterior  = $entrada->almacen_id;

        // ── Si era confirmada, validar ANTES que ninguna reduccion deje stock negativo.
        // Esto cubre el caso "ya se vendieron / transfirieron unidades de esta entrada".
        // Mejor fallar aqui con mensaje claro que reventar despues con InsufficientStock.
        if ($eraConfirmada) {
            $entrada->loadMissing('detalles');

            // Sumar cantidad_base por producto en lo VIEJO (puede haber 2 lineas del mismo
            // producto con presentaciones distintas, todas suman al stock del producto).
            $viejosBase = $entrada->detalles->groupBy('producto_id')
                ->map(fn ($g) => (float) $g->sum('cantidad_base'));

            // Y en lo NUEVO: convertir cantidad → cantidad_base con su factor.
            $nuevosBase = collect($data['detalles'])
                ->groupBy('producto_id')
                ->map(fn ($g) => (float) $g->sum(fn ($d) =>
                    round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4)
                ));

            $errores = [];
            foreach ($viejosBase as $productoId => $cantVieja) {
                // Entrada absorbida por el inventario inicial (fecha <= corte de
                // apertura): sus unidades viven en el conteo físico, NO en el stock
                // en vivo. Reducirla es corrección documental — no valida stock.
                if (Stock::absorbidoPorApertura($almacenAnterior, (int) $productoId, $entrada->fecha)) {
                    continue;
                }

                $cantNueva = (float) $nuevosBase->get($productoId, 0);
                $delta     = $cantNueva - $cantVieja;
                if ($delta >= 0) continue; // suma o igual: no hay riesgo

                // Reduccion: chequear stock contra el almacen ORIGINAL (alli vivian los
                // movimientos que generaba la entrada).
                $stockActual = (float) (Stock::where('almacen_id', $almacenAnterior)
                    ->where('producto_id', $productoId)
                    ->value('cantidad') ?? 0);

                if ($stockActual + $delta < 0) {
                    $producto    = Producto::find($productoId);
                    $consumido   = $cantVieja - $stockActual;          // unidades ya salidas (ventas/transfer/etc.)
                    $minPermit   = max(0.0001, $cantVieja - $stockActual);
                    $fmt = fn (float $n) => rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');

                    $errores[] = sprintf(
                        'No se puede reducir "%s": ya se generaron salidas (ventas / transferencias / etc.) por %s unidades base de esta entrada. La cantidad mínima permitida en esta línea es %s (base).',
                        $producto?->nombre ?? "producto #$productoId",
                        $fmt($consumido),
                        $fmt($minPermit),
                    );
                }
            }

            if (!empty($errores)) {
                return back()->withErrors(['detalles' => implode("\n", $errores)])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($data, $entrada, $eraConfirmada, $almacenAnterior, $user) {
                // 1) Si era confirmada, revertir el stock que aporto cada detalle viejo.
                //    permitirNegativo=true porque es transitorio: al final reaplicamos
                //    los nuevos. La validacion previa ya garantizo que el saldo final >= 0.
                $productosAfectados = collect();
                if ($eraConfirmada) {
                    $entrada->loadMissing('detalles');
                    foreach ($entrada->detalles as $d) {
                        $productosAfectados->push($d->producto_id);
                        // Absorbida por la apertura: nunca aportó al stock en vivo,
                        // no hay nada que revertir (evita el ruido entrada_reverso
                        // y el falso descuadre al editar entradas del día del corte).
                        if (Stock::absorbidoPorApertura($almacenAnterior, $d->producto_id, $entrada->fecha)) {
                            continue;
                        }
                        Stock::ajustar(
                            almacenId:        $almacenAnterior,
                            productoId:       $d->producto_id,
                            cantidadBase:     -1 * (float) $d->cantidad_base,
                            permitirNegativo: true,
                            contexto: [
                                'tipo'            => 'entrada_reverso',
                                'referencia_tipo' => 'entrada',
                                'referencia_id'   => $entrada->id,
                                'documento'       => $entrada->numero_documento,
                                'fecha'           => now(),
                                'user_id'         => $user->id,
                                'empresa_id'      => $entrada->empresa_id,
                            ],
                        );
                    }
                }

                // 2) Update cabecera + reemplazo de detalles (el pago no se toca aquí).
                $entrada->update([
                    'almacen_id'       => $data['almacen_id'],
                    'proveedor_id'     => $data['proveedor_id'] ?? null,
                    'proveedor'        => $data['proveedor'] ?? null,
                    'numero_documento' => $data['numero_documento'] ?? null,
                    'tipo'             => $data['tipo'],
                    'fecha'            => $data['fecha'],
                    'observacion'      => $data['observacion'] ?? null,
                ]);

                // El correlativo es INMUTABLE: si ya lo tiene NO se regenera aunque
                // cambie la fecha. Solo se asigna si por alguna razón faltaba (p. ej.
                // entradas legacy previas a esta feature).
                if (empty($entrada->correlativo)) {
                    $this->asignarCorrelativo($entrada, $entrada->empresa_id, $entrada->fecha);
                }

                $entrada->detalles()->delete();

                $total = 0;
                foreach ($data['detalles'] as $d) {
                    $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);
                    $subtotal     = round((float) $d['cantidad'] * (float) $d['precio_costo'], 2);
                    $total       += $subtotal;

                    $entrada->detalles()->create([
                        'producto_id'      => $d['producto_id'],
                        'unidad_medida_id' => $d['unidad_medida_id'],
                        'cantidad'         => $d['cantidad'],
                        'factor_conversion'=> $d['factor_conversion'],
                        'cantidad_base'    => $cantidadBase,
                        'precio_costo'     => $d['precio_costo'],
                        'subtotal'         => $subtotal,
                        'numero_documento' => $d['numero_documento'] ?? null,
                    ]);
                    if ($eraConfirmada) {
                        $productosAfectados->push($d['producto_id']);
                    }
                }

                $entrada->update(['total' => $total]);

                // ── Pagos ya registrados: EDITADOS / ANULADOS ──────────────────
                // Se guardan con el MISMO botón "Guardar cambios" (un solo submit,
                // sin botón por método). Tocar pagos ya asentados mueve tesorería →
                // solo admin, igual que Finanzas → Cuentas por pagar.
                $editados = $data['pagos_editados'] ?? [];
                $anulados = $data['pagos_anulados'] ?? [];
                if (!empty($editados) || !empty($anulados)) {
                    abort_unless($user->rol->es_admin, 403, 'Solo un administrador puede editar o anular pagos ya registrados.');
                }

                // Anular: revierte el egreso en tesorería (o restaura el adelanto) y borra el pago.
                foreach ($anulados as $pagoId) {
                    $pago = $entrada->pagosParciales()->whereKey($pagoId)->first();
                    if (!$pago) continue;
                    if ($pago->proveedor_adelanto_id) {
                        $adelanto = \App\Models\ProveedorAdelanto::whereKey($pago->proveedor_adelanto_id)->lockForUpdate()->first();
                        if ($adelanto) {
                            $adelanto->update([
                                'saldo'  => round((float) $adelanto->saldo + (float) $pago->monto, 2),
                                'estado' => 'activo',
                            ]);
                            $adelanto->aplicaciones()
                                ->where('entrada_id', $entrada->id)
                                ->where('monto', $pago->monto)
                                ->orderByDesc('id')->first()?->delete();
                        }
                    } else {
                        $this->tesoreria->revertir('entrada_pago', $pago->id);
                    }
                    \App\Services\AuditoriaService::log('entrada.pago_anulado', $entrada, [
                        'pago_id' => $pago->id, 'monto' => (float) $pago->monto,
                    ], $user);
                    $pago->delete();
                }

                // Editar: actualiza método/cuenta/monto/turno y rehace el egreso.
                foreach ($editados as $ed) {
                    $pago = $entrada->pagosParciales()->whereKey($ed['id'])->first();
                    if (!$pago) continue;
                    $esAdelanto = !empty($pago->proveedor_adelanto_id);
                    // Los pagos con adelanto NO cambian de monto/método aquí (descuadraría
                    // el adelanto): solo el turno. Para cambiarlos: anular y re-registrar.
                    $montoNuevo = $esAdelanto ? (float) $pago->monto : round((float) ($ed['monto'] ?? $pago->monto), 2);

                    $pago->update([
                        'monto'          => $montoNuevo,
                        'metodo_pago_id' => $esAdelanto ? $pago->metodo_pago_id : ($ed['metodo_pago_id'] ?? null),
                        'cuenta_id'      => $esAdelanto ? $pago->cuenta_id : ($ed['cuenta_id'] ?? null),
                        'turno_id'       => array_key_exists('turno_id', $ed) ? ($ed['turno_id'] ?: null) : $pago->turno_id,
                    ]);

                    if (!$esAdelanto) {
                        $this->tesoreria->revertir('entrada_pago', $pago->id);
                        $prov = $entrada->proveedorRel?->razon_social ?? $entrada->proveedor ?? 'proveedor';
                        $this->tesoreria->registrar(
                            $entrada->empresa_id,
                            ($ed['cuenta_id'] ?? null) ?: $this->tesoreria->resolverCuenta($entrada->empresa_id, null, $ed['metodo_pago_id'] ?? null),
                            $user,
                            (string) $pago->fecha->toDateString(),
                            'egreso',
                            $montoNuevo,
                            "Pago a proveedor {$prov}" . ($entrada->numero_documento ? " ({$entrada->numero_documento})" : '') . ' [editado]',
                            'entrada_pago',
                            $pago->id,
                        );
                    }
                    \App\Services\AuditoriaService::log('entrada.pago_editado', $entrada, [
                        'pago_id' => $pago->id, 'monto' => $montoNuevo,
                    ], $user);
                }

                // Recomputar monto_pagado desde la suma REAL de pagos (autoritativo:
                // cubre cambios de detalle, ediciones y anulaciones de golpe).
                $sumaExistentes = round((float) $entrada->pagosParciales()->sum('monto'), 2);
                $entrada->update([
                    'monto_pagado' => $sumaExistentes,
                    'estado_pago'  => $sumaExistentes >= (float) $entrada->total - 0.01 ? 'pagado' : ($sumaExistentes > 0 ? 'parcial' : 'pendiente'),
                ]);

                // "Afecta caja a:" para los pagos NUEVOS (null = no afecta caja).
                $turnoArg = array_key_exists('turno_id', $data) ? ($data['turno_id'] ?: null) : null;

                // Si el usuario TOCÓ el selector, re-imputar el turno de los pagos
                // YA registrados (los que no fueron editados/anulados uno por uno).
                // Es solo la asignación de caja (no mueve tesorería), por eso NO
                // requiere admin: arregla el caso "edito la entrada para que afecte
                // a caja y no se actualizaba". Solo turnos abiertos (el selector ya
                // los limita); re-imputar a uno cerrado no reflejaría.
                if (!empty($data['reimputar_pagos_turno'])) {
                    $idsExcluir = array_merge(
                        collect($editados)->pluck('id')->filter()->all(),
                        collect($anulados)->all(),
                    );
                    $entrada->pagosParciales()
                        ->when($idsExcluir, fn ($q) => $q->whereNotIn('id', $idsExcluir))
                        ->update(['turno_id' => $turnoArg]);

                    \App\Services\AuditoriaService::log('entrada.afecta_caja', $entrada, [
                        'turno_id' => $turnoArg,
                    ], $user);
                }

                // Pagos NUEVOS desde la edición (entrada_pagos + egreso en tesorería).
                $lineas = $data['pagos'] ?? [];
                if (!empty($lineas)) {
                    $suma  = round(collect($lineas)->sum(fn ($l) => (float) $l['monto']), 2);
                    $saldo = $entrada->refresh()->saldoPendiente();
                    if ($suma > $saldo + 0.01) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'pagos' => "Los pagos nuevos (S/ {$suma}) superan el saldo pendiente de la compra (S/ " . number_format($saldo, 2) . ').',
                        ]);
                    }
                    $this->registrarPagosIniciales($entrada, $lineas, $user->id, $turnoArg);
                }

                // Tope de seguridad: los pagos totales no pueden exceder el total.
                if (round((float) $entrada->refresh()->monto_pagado, 2) > (float) $entrada->total + 0.01) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'pagos' => 'Los pagos superan el total de la compra. Ajusta los montos.',
                    ]);
                }

                // 3) Si era confirmada, aplicar el stock nuevo y reconstruir CPP para
                //    cada (almacen, producto) afectado. Reconstruir es necesario porque
                //    Stock::ajustar negativo NO recalcula CPP — el CPP se reconstruye
                //    desde el historial de entradas confirmadas.
                if ($eraConfirmada) {
                    $entrada->refresh()->loadMissing('detalles');
                    foreach ($entrada->detalles as $d) {
                        // Con fecha (nueva) en o antes del corte de apertura: la
                        // mercadería ya está contada en el inventario inicial —
                        // reaplicarla duplicaría stock. Solo corrección documental.
                        if (Stock::absorbidoPorApertura($entrada->almacen_id, $d->producto_id, $entrada->fecha)) {
                            continue;
                        }
                        Stock::ajustar(
                            almacenId:    $entrada->almacen_id,
                            productoId:   $d->producto_id,
                            cantidadBase: (float) $d->cantidad_base,
                            costoNuevo:   (float) $d->precio_costo,
                            contexto: [
                                'tipo'            => 'entrada_edicion',
                                'referencia_tipo' => 'entrada',
                                'referencia_id'   => $entrada->id,
                                'documento'       => $entrada->numero_documento,
                                'fecha'           => $entrada->fecha,
                                'user_id'         => $user->id,
                                'empresa_id'      => $entrada->empresa_id,
                            ],
                        );
                    }

                    // Reconstruir CPP en ambos almacenes (si cambio) para los productos
                    // tocados — garantiza que el costo promedio quede correcto incluso si
                    // hay otras entradas confirmadas posteriores en el historial.
                    $almacenes = collect([$almacenAnterior, $entrada->almacen_id])->unique();
                    foreach ($almacenes as $almId) {
                        foreach ($productosAfectados->unique() as $pid) {
                            Stock::reconstruir((int) $almId, (int) $pid);
                        }
                    }
                }
            });
        } catch (\App\Exceptions\InsufficientStockException $e) {
            // Red de seguridad: nuestra validacion previa deberia atrapar todos los
            // casos, pero si algo se cuela (race condition con una venta concurrente),
            // devolvemos un error legible en vez de un 500.
            return back()->withErrors(['detalles' => $e->getMessage()])->withInput();
        }

        return redirect()->route('inventario.entradas.index')
            ->with('success', $eraConfirmada
                ? 'Entrada actualizada. Stock y costo promedio recalculados.'
                : 'Entrada actualizada correctamente.'
            );
    }

    public function confirmar(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $entrada->confirmar();

        return redirect()->back()->with('success', 'Entrada confirmada. El stock ha sido actualizado.');
    }

    /**
     * JSON con la entrada + detalles para alimentar el modal "Ver detalle" del Index.
     * No usamos show() inertia (no hay pagina Show) — el modal vive en el Index y
     * trae los datos via axios.get cuando el usuario abre. Esto evita cargar los
     * detalles de TODAS las entradas en cada index (seria N+1 cuando la empresa
     * tiene cientos de entradas con muchos items).
     */
    public function detalleJson(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $entrada->load([
            'almacen.local',
            'user:id,name',
            'proveedorRel:id,razon_social,nombre_comercial,numero_documento,tipo_documento',
            'metodoPago:id,nombre',
            'cuenta:id,nombre,banco,numero_cuenta',
            'detalles.producto:id,nombre,codigo',
            'detalles.unidadMedida:id,nombre,abreviatura',
        ]);

        return response()->json(['entrada' => $entrada]);
    }

    /**
     * Pago rápido desde el listado. A diferencia de update() esto funciona
     * en cualquier estado de la entrada (borrador o confirmada) porque el pago
     * es un track independiente — tipico flujo "te debo, te pago la semana
     * que viene" donde la mercaderia ya entro al stock pero falta liquidar.
     *
     * TRAZABILIDAD: "pagado" registra un entrada_pago por el saldo pendiente
     * con su egreso en tesorería (nada de marcar sin dejar rastro); volver a
     * "pendiente" elimina los pagos en dinero y revierte sus asientos,
     * auditado — y se bloquea si algún pago consumió un adelanto.
     */
    public function actualizarPago(Request $request, Entrada $entrada)
    {
        $user = $request->user();
        abort_unless($this->scope->puedeAccederAlmacen($user, $entrada->almacen), 403);

        $data = $request->validate([
            'estado_pago'    => 'required|in:pendiente,pagado',
            'metodo_pago_id' => ['nullable', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
        ]);

        if ($data['estado_pago'] === 'pagado') {
            $saldo = $entrada->saldoPendiente();
            if ($saldo <= 0.01) {
                return redirect()->back()->with('success', 'La entrada ya estaba pagada.');
            }

            DB::transaction(function () use ($entrada, $data, $user, $saldo) {
                $entrada->update([
                    'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                    'cuenta_id'      => $data['cuenta_id'] ?? null,
                ]);
                $this->registrarPagosIniciales($entrada, [[
                    'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                    'cuenta_id'      => $data['cuenta_id'] ?? null,
                    'monto'          => $saldo,
                ]], $user->id);

                AuditoriaService::log('entrada.pago_total', $entrada, [
                    'monto' => $saldo,
                ], $user);
            });

            return redirect()->back()->with('success', 'Pago registrado: S/ ' . number_format($saldo, 2) . ' al proveedor.');
        }

        // Revertir a pendiente: eliminar pagos en dinero + asientos de tesorería.
        $pagos = $entrada->pagosParciales()->get();
        if ($pagos->contains(fn ($p) => $p->proveedor_adelanto_id !== null)) {
            return back()->withErrors([
                'estado_pago' => 'Esta entrada tiene pagos que consumieron un adelanto al proveedor. Gestiónalos desde Finanzas → Cuentas por Pagar.',
            ]);
        }

        DB::transaction(function () use ($entrada, $pagos, $user) {
            foreach ($pagos as $p) {
                $this->tesoreria->revertir('entrada_pago', $p->id);
                $p->delete();
            }
            $entrada->update([
                'monto_pagado'   => 0,
                'estado_pago'    => 'pendiente',
                'metodo_pago_id' => null,
                'cuenta_id'      => null,
            ]);

            AuditoriaService::log('entrada.pago_revertido', $entrada, [
                'pagos_eliminados' => $pagos->count(),
                'monto_revertido'  => round((float) $pagos->sum('monto'), 2),
            ], $user);
        });

        return redirect()->back()->with('success', 'Pago revertido a pendiente (asientos de tesorería revertidos).');
    }

    public function destroy(Request $request, Entrada $entrada)
    {
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden eliminar entradas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        // M18: snapshot ANTES de borrar para que la auditoría tenga registro de
        // qué se perdió (productos, cantidades). Sin esto, una disputa futura
        // "¿quién borró la entrada del lunes?" queda sin respuesta.
        $entrada->loadMissing('detalles.producto');
        $snapshot = [
            'almacen_id'   => $entrada->almacen_id,
            'tipo'         => $entrada->tipo,
            'fecha'        => $entrada->fecha?->toDateString(),
            'total_items'  => $entrada->detalles->count(),
            'detalles'     => $entrada->detalles->map(fn ($d) => [
                'producto_id'     => $d->producto_id,
                'producto_nombre' => $d->producto?->nombre,
                'cantidad_base'   => (float) $d->cantidad_base,
            ])->all(),
        ];

        DB::transaction(function () use ($entrada) {
            // Revertir pagos registrados (y sus asientos de tesorería) antes de borrar.
            foreach ($entrada->pagosParciales()->get() as $p) {
                $this->tesoreria->revertir('entrada_pago', $p->id);
                $p->delete();
            }
            $entrada->detalles()->delete();
            $entrada->delete();
        });

        \App\Services\AuditoriaService::log('entrada.eliminada', $entrada, $snapshot, $request->user());

        return redirect()->back()->with('success', 'Entrada eliminada correctamente.');
    }
}
