<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Cuenta;
use App\Models\Entrada;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EntradaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

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
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventario/Entradas/Index', [
            'entradas'        => $entradas,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'filters'         => $request->only(['almacen_id', 'estado', 'fecha_desde', 'fecha_hasta']),
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
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
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
            // de pago). Si esta pagado, debe haber metodo_pago. La cuenta es opcional aun
            // cuando el metodo tenga cuentas anidadas (el user puede no recordar cual).
            'estado_pago'      => 'nullable|in:pendiente,pagado',
            'metodo_pago_id'   => 'nullable|exists:metodos_pago,id',
            'cuenta_id'        => 'nullable|exists:cuentas,id',
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
                'estado_pago'      => $estadoPago,
                // Solo persistir metodo/cuenta si esta pagado — si esta pendiente, esos
                // campos quedan null aunque el form los haya enviado (defensivo).
                'metodo_pago_id'   => $estadoPago === 'pagado' ? ($data['metodo_pago_id'] ?? null) : null,
                'cuenta_id'        => $estadoPago === 'pagado' ? ($data['cuenta_id'] ?? null) : null,
            ]);

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
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden editar entradas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Entradas/Edit', [
            'entrada'   => $entrada->load(['detalles.producto', 'detalles.unidadMedida', 'proveedorRel', 'metodoPago', 'cuenta']),
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
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function update(Request $request, Entrada $entrada)
    {
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden editar entradas en borrador.');
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
            // Pago: independiente del estado de la entrada (puede estar confirmada y pendiente
            // de pago). Si esta pagado, debe haber metodo_pago. La cuenta es opcional aun
            // cuando el metodo tenga cuentas anidadas (el user puede no recordar cual).
            'estado_pago'      => 'nullable|in:pendiente,pagado',
            'metodo_pago_id'   => 'nullable|exists:metodos_pago,id',
            'cuenta_id'        => 'nullable|exists:cuentas,id',
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

        DB::transaction(function () use ($data, $entrada) {
            $total = 0;

            $estadoPago = $data['estado_pago'] ?? 'pendiente';
            $entrada->update([
                'almacen_id'       => $data['almacen_id'],
                'proveedor_id'     => $data['proveedor_id'] ?? null,
                'proveedor'        => $data['proveedor'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'tipo'             => $data['tipo'],
                'fecha'            => $data['fecha'],
                'observacion'      => $data['observacion'] ?? null,
                'estado_pago'      => $estadoPago,
                'metodo_pago_id'   => $estadoPago === 'pagado' ? ($data['metodo_pago_id'] ?? null) : null,
                'cuenta_id'        => $estadoPago === 'pagado' ? ($data['cuenta_id'] ?? null) : null,
            ]);

            $entrada->detalles()->delete();

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
        });

        return redirect()->route('inventario.entradas.index')
            ->with('success', 'Entrada actualizada correctamente.');
    }

    public function confirmar(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $entrada->confirmar();

        return redirect()->back()->with('success', 'Entrada confirmada. El stock ha sido actualizado.');
    }

    /**
     * Actualiza SOLO los campos de pago. A diferencia de update() esto funciona
     * en cualquier estado de la entrada (borrador o confirmada) porque el pago
     * es un track independiente — tipico flujo "te debo, te pago la semana
     * que viene" donde la mercaderia ya entro al stock pero falta liquidar.
     */
    public function actualizarPago(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $data = $request->validate([
            'estado_pago'    => 'required|in:pendiente,pagado',
            'metodo_pago_id' => 'nullable|exists:metodos_pago,id',
            'cuenta_id'      => 'nullable|exists:cuentas,id',
        ]);

        $entrada->update([
            'estado_pago'    => $data['estado_pago'],
            'metodo_pago_id' => $data['estado_pago'] === 'pagado' ? ($data['metodo_pago_id'] ?? null) : null,
            'cuenta_id'      => $data['estado_pago'] === 'pagado' ? ($data['cuenta_id'] ?? null) : null,
        ]);

        return redirect()->back()->with('success',
            $data['estado_pago'] === 'pagado'
                ? 'Entrada marcada como pagada.'
                : 'Pago revertido a pendiente.'
        );
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

        $entrada->detalles()->delete();
        $entrada->delete();

        \App\Services\AuditoriaService::log('entrada.eliminada', $entrada, $snapshot, $request->user());

        return redirect()->back()->with('success', 'Entrada eliminada correctamente.');
    }
}
