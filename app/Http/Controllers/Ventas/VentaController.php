<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\AnularVentaRequest;
use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Jobs\EmitirComprobanteElectronico;
use App\Models\Cliente;
use App\Models\DescuentoConcepto;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\CitaService;
use App\Services\LocalScopeService;
use App\Services\TicketPrintService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class VentaController extends Controller
{
    // Ventana de edición: una venta se puede editar libremente dentro de este
    // lapso desde su creación. Pasado el plazo, editar se bloquea y anular
    // requiere código de autorización de un administrador.
    private const EDIT_WINDOW_SECONDS = 180; // 3 minutos

    public function __construct(
        private VentaService      $ventaService,
        private LocalScopeService $scope,
        private CitaService       $citaService,
    ) {}

    /** true si la venta aún está dentro de los 3 min de edición desde su creación. */
    private function dentroPlazoEdicion(Venta $venta): bool
    {
        return $venta->created_at
            && abs($venta->created_at->diffInSeconds(now())) <= self::EDIT_WINDOW_SECONDS;
    }

    /**
     * Valida un "código de autorización" para anular fuera de plazo. Placeholder
     * hasta implementar el envío por WhatsApp/Telegram: por ahora el código es
     * la contraseña de acceso de cualquier administrador activo de la empresa.
     */
    private function codigoAdminValido(int $empresaId, string $codigo): bool
    {
        if ($codigo === '') return false;
        $admins = User::where('empresa_id', $empresaId)->where('activo', true)
            ->whereHas('rol', fn($q) => $q->where('es_admin', true))
            ->get(['id', 'password']);
        foreach ($admins as $a) {
            if ($a->password && Hash::check($codigo, $a->password)) return true;
        }
        return false;
    }

    // ── POS ────────────────────────────────────────────────────────────────────

    /** TC USD del día para el POS; null si la SBS no responde (el POS no debe romperse). */
    private function tipoCambioHoy(): ?float
    {
        try {
            return app(\App\Services\TipoCambioService::class)->tasaHoy('USD');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function pos(Request $request)
    {
        $user   = $request->user();
        $turnoActivo = Turno::turnoActivoDelUsuario($user->id)?->load('caja');

        // Modo edición (?venta_id=): cargar temprano la venta objetivo para poder
        // resolver el turno. El admin edita SOBRE el turno de quien hizo la venta,
        // por eso NO se le exige tener un turno propio abierto.
        $ventaObjetivo = null;
        if ($ventaId = $request->query('venta_id')) {
            $ventaObjetivo = Venta::with(['items.producto', 'items.productoUnidad.unidadMedida', 'pagos', 'cliente', 'turno.caja'])
                ->where('id', $ventaId)
                ->where('empresa_id', $user->empresa_id)
                ->first();
        }
        $puedeEditarVenta = $ventaObjetivo && $ventaObjetivo->estado === 'completada'
            && ($user->rol->es_admin || $this->dentroPlazoEdicion($ventaObjetivo));

        // Turno del POS: el propio activo; y si estamos editando sin turno propio,
        // se toma el turno de la venta (así el admin puede editar sin abrir caja).
        $turno = $turnoActivo;
        if (!$turno && $puedeEditarVenta) {
            $turno = $ventaObjetivo->turno?->loadMissing('caja');
        }

        // Modo turno específico (?turno_id=, solo admin): operar el POS sobre un
        // turno abierto ajeno — típicamente uno REABIERTO de un día anterior para
        // registrar una venta olvidada. Las ventas se guardan con la FECHA del
        // turno (backdate), no con la de hoy.
        $turnoBackdate = null;
        if (($turnoIdQ = $request->query('turno_id')) && $user->rol->es_admin) {
            $turnoEspecifico = Turno::with(['caja', 'user'])
                ->where('id', $turnoIdQ)
                ->where('empresa_id', $user->empresa_id)
                ->where('estado', 'abierto')
                ->first();
            if ($turnoEspecifico) {
                $turno = $turnoEspecifico;
                $turnoBackdate = [
                    'turno_id' => $turnoEspecifico->id,
                    'fecha'    => $turnoEspecifico->fecha_apertura?->toDateString(),
                    'cajera'   => $turnoEspecifico->user?->name,
                    'caja'     => $turnoEspecifico->caja?->nombre,
                    'es_hoy'   => $turnoEspecifico->fecha_apertura?->isToday() ?? true,
                ];
            }
        }

        if (!$turno) {
            return redirect()->route('turnos.index')
                ->with('error', 'Debes tener un turno activo para acceder al POS.');
        }

        $productos = Producto::deEmpresa($user->empresa_id)
            ->activo()
            ->with(['unidades.unidadMedida', 'unidadBase', 'categoria'])
            ->orderBy('nombre')
            ->get();

        // Stock disponible (en unidad base) por producto. Se toma del almacén del
        // local DE LA VENTA cuando estamos editando (para que el admin vea el stock
        // correcto aunque no tenga local); si no, del almacén del propio usuario.
        $almacenVentas = ($puedeEditarVenta
            ? $this->scope->almacenVentasDeLocal($ventaObjetivo->empresa_id, $ventaObjetivo->local_id)
            : null)
            // Modo turno específico: stock del local DE ESE TURNO (el admin
            // puede no tener local propio).
            ?? ($turnoBackdate
                ? $this->scope->almacenVentasDeLocal($user->empresa_id, $turno->local_id)
                : null)
            ?? $this->scope->almacenParaVentas($user);
        $stockMap = $almacenVentas
            ? \App\Models\Stock::where('almacen_id', $almacenVentas->id)->pluck('cantidad', 'producto_id')
            : collect();
        $productos->each(function ($p) use ($almacenVentas, $stockMap) {
            $p->stock_disponible = $almacenVentas ? (float) ($stockMap[$p->id] ?? 0) : null;
        });

        $clientes = Cliente::where('empresa_id', $user->empresa_id)
            ->activo()
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'tipo_documento', 'numero_documento', 'telefono']);

        $metodosPago = MetodoPago::deEmpresa($user->empresa_id)
            ->activo()
            ->with(['cuentas' => fn($q) => $q->where('activo', true)])
            ->orderBy('nombre')
            ->get();

        $conceptosDescuento = DescuentoConcepto::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('nombre')
            ->get();

        // Si el POS se abre desde una cita (cita_id en query), prellenar el carrito.
        // El frontend usa esta data para inicializar carrito + cliente automaticamente.
        $citaPrellenada = null;
        if ($citaId = $request->query('cita_id')) {
            $cita = \App\Models\Cita::with(['cliente', 'items.producto', 'items.productoUnidad.unidadMedida'])
                ->where('id', $citaId)
                ->where('empresa_id', $user->empresa_id)
                ->first();

            if ($cita && !$cita->venta_id && $cita->estaActiva()) {
                // Marcamos por item si producto/unidad siguen activos. Entre agendar
                // y cobrar pueden haber pasado dias y el admin pudo desactivar items.
                // El frontend usa estos flags para mostrar alerta visible y bloquear
                // el cobro hasta que el cajero resuelva (eliminar la linea o pedir
                // reactivacion al admin).
                $items = $cita->items->map(function ($it) {
                    $productoActivo = (bool) ($it->producto?->activo);
                    $unidadActiva   = (bool) ($it->productoUnidad?->activo);

                    return [
                        'producto_id'        => $it->producto_id,
                        'producto_unidad_id' => $it->producto_unidad_id,
                        'producto_nombre'    => $it->producto->nombre,
                        'unidad_nombre'      => $it->productoUnidad->unidadMedida->nombre ?? '',
                        'cantidad'           => (float) $it->cantidad,
                        'precio_unitario'    => (float) $it->productoUnidad->precio_venta, // precio actual del catalogo
                        'incluye_igv'        => (bool) $it->producto->incluye_igv,
                        'producto_activo'    => $productoActivo,
                        'unidad_activa'      => $unidadActiva,
                        'inactivo'           => !($productoActivo && $unidadActiva),
                    ];
                });

                $citaPrellenada = [
                    'id'             => $cita->id,
                    'numero'         => $cita->numero,
                    'sujeto_label'   => $user->empresa->agenda_sujeto_label,
                    'sujeto'         => $cita->sujeto_nombre,
                    'cliente'        => $cita->cliente,
                    'items'          => $items,
                    'tiene_inactivos'=> $items->contains(fn($i) => $i['inactivo']),
                ];
            }
        }

        // Si el POS se abre desde una cotización (cotizacion_id en query),
        // prellenar carrito + cliente con los PRECIOS COTIZADOS (congelados),
        // patrón idéntico al de citaPrellenada.
        $cotizacionPrellenada = null;
        if ($cotizacionId = $request->query('cotizacion_id')) {
            $cotizacion = \App\Models\Cotizacion::with(['cliente', 'items.producto', 'items.productoUnidad.unidadMedida'])
                ->where('id', $cotizacionId)
                ->where('empresa_id', $user->empresa_id)
                ->first();

            if ($cotizacion && $cotizacion->esConvertible()) {
                // Flags de frescura por ítem: entre cotizar y cobrar pueden
                // pasar días y el admin pudo desactivar producto/presentación.
                // El cajero lo ve en rojo y resuelve antes de cobrar.
                $items = $cotizacion->items->map(function ($it) {
                    $productoActivo = (bool) ($it->producto?->activo);
                    $unidadActiva   = (bool) ($it->productoUnidad?->activo);

                    return [
                        'producto_id'        => $it->producto_id,
                        'producto_unidad_id' => $it->producto_unidad_id,
                        'producto_nombre'    => $it->producto_nombre,
                        'unidad_nombre'      => $it->unidad_nombre,
                        'cantidad'           => (float) $it->cantidad,
                        'precio_unitario'    => (float) $it->precio_unitario, // precio cotizado (congelado)
                        'descuento_item'     => (float) $it->descuento_item,
                        'incluye_igv'        => (bool) ($it->producto?->incluye_igv ?? true),
                        'producto_activo'    => $productoActivo,
                        'unidad_activa'      => $unidadActiva,
                        'inactivo'           => !($productoActivo && $unidadActiva),
                    ];
                });

                $cotizacionPrellenada = [
                    'id'              => $cotizacion->id,
                    'numero'          => $cotizacion->numero,
                    'referencia'      => $cotizacion->referencia,
                    'cliente'         => $cotizacion->cliente,
                    'items'           => $items,
                    'tiene_inactivos' => $items->contains(fn ($i) => $i['inactivo']),
                ];
            }
        }

        // Edición de venta desde el POS (?venta_id=): precarga el carrito, cliente
        // y pagos de la venta ya cargada arriba ($ventaObjetivo). La cajera solo
        // dentro de los 3 min; el admin sin límite. El submit irá a ventas.update.
        $ventaEnEdicion = null;
        if ($puedeEditarVenta) {
            $v = $ventaObjetivo;
            {
                // Los precios/montos se guardan en soles; para reeditar en la moneda
                // original los devolvemos a esa moneda dividiendo por el TC congelado.
                $factor = ($v->moneda && $v->moneda !== 'PEN' && $v->tipo_cambio)
                    ? (float) $v->tipo_cambio : 1.0;

                // Pendiente por entregar de la venta (para prellenar el panel).
                // Si ya hubo ENTREGAS registradas, la edición está bloqueada en
                // el backend; el POS lo avisa desde el inicio.
                $anticiposPend = $v->anticipos()->where('estado', 'activo')->with('items')->get();
                $pendPorItem   = [];
                foreach ($anticiposPend as $ant) {
                    foreach ($ant->items as $ai) {
                        if ($ai->venta_item_id) {
                            $pendPorItem[$ai->venta_item_id] = ($pendPorItem[$ai->venta_item_id] ?? 0)
                                + (float) $ai->cantidad_pendiente;
                        }
                    }
                }
                $pendienteBloqueado = $v->anticipos()
                    ->whereIn('estado', ['activo', 'aplicado'])
                    ->whereHas('aplicaciones')
                    ->exists();

                $ventaEnEdicion = [
                    'id'                    => $v->id,
                    'numero'                => $v->numero,
                    'tipo_comprobante'      => $v->tipo_comprobante,
                    'descuento_total'       => $factor > 0 ? round((float) $v->descuento_total / $factor, 2) : (float) $v->descuento_total,
                    'descuento_concepto_id' => $v->descuento_concepto_id,
                    'moneda'                => $v->moneda ?? 'PEN',
                    'es_admin'              => (bool) $user->rol->es_admin,
                    'expira_en'             => $v->created_at?->addSeconds(self::EDIT_WINDOW_SECONDS)->toIso8601String(),
                    'cliente'               => $v->cliente,
                    // Crédito: hay que devolverlo para que el toggle cargue marcado al
                    // editar (antes salía siempre desmarcado). fecha_vencimiento solo
                    // aplica si es crédito.
                    'es_credito'            => (bool) $v->es_credito,
                    'fecha_vencimiento'     => $v->fecha_vencimiento?->toDateString(),
                    'entrega_pendiente'     => $anticiposPend->isNotEmpty(),
                    'fecha_entrega_estimada'=> $anticiposPend->first()?->fecha_entrega_estimada?->toDateString(),
                    'pendiente_bloqueado'   => $pendienteBloqueado,
                    'items'                 => $v->items->map(fn($it) => [
                        'producto_id'           => $it->producto_id,
                        'producto_unidad_id'    => $it->producto_unidad_id,
                        'producto_nombre'       => $it->producto_nombre,
                        'unidad_nombre'         => $it->unidad_nombre,
                        'cantidad'              => (float) $it->cantidad,
                        'cantidad_pendiente'    => (float) ($pendPorItem[$it->id] ?? 0),
                        'precio_unitario'       => $factor > 0 ? round((float) $it->precio_unitario / $factor, 2) : (float) $it->precio_unitario,
                        'descuento_item'        => $factor > 0 ? round((float) $it->descuento_item / $factor, 2) : (float) $it->descuento_item,
                        'descuento_concepto_id' => $it->descuento_concepto_id,
                        'incluye_igv'           => (bool) $it->incluye_igv,
                    ])->values(),
                    'pagos'                 => $v->pagos->map(fn($p) => [
                        'metodo_pago_id'        => $p->metodo_pago_id,
                        'cuenta_metodo_pago_id' => $p->cuenta_metodo_pago_id,
                        'monto'                 => $factor > 0 ? round((float) $p->monto / $factor, 2) : (float) $p->monto,
                        'referencia'            => $p->referencia,
                    ])->values(),
                ];
            }
        }

        // A14 — Bloquear el POS cuando el usuario no tiene un almacén de ventas
        // resoluble (típicamente admin sin local_id en modo central_y_local).
        // Cargar el POS, llenar el carrito e intentar cobrar al final daba un
        // 422 sorpresa. Ahora el frontend recibe esta bandera y deshabilita el
        // botón "Cobrar" con un mensaje claro desde el primer momento.
        $puedeVender    = $this->scope->puedeVender($user)
            // En modo turno específico basta con que el local del turno tenga
            // almacén de ventas (el admin vende "en nombre de" ese turno).
            || ($turnoBackdate && $almacenVentas !== null);
        $razonNoVender  = null;
        if (!$puedeVender) {
            $razonNoVender = $user->empresa->usaCentralYLocal() && !$user->local_id
                ? 'No tienes un local asignado. Selecciona un local para operar el POS.'
                : 'No hay almacén de ventas configurado para tu local. Contacta al administrador.';
        }

        return Inertia::render('Pos/Index', [
            'turno'              => $turno,
            'productos'          => $productos,
            'clientes'           => $clientes,
            'metodosPago'        => $metodosPago,
            'conceptosDescuento' => $conceptosDescuento,
            'citaPrellenada'     => $citaPrellenada,
            'cotizacionPrellenada' => $cotizacionPrellenada,
            'ventaEnEdicion'     => $ventaEnEdicion,
            'turnoBackdate'      => $turnoBackdate,
            'puedeVender'        => $puedeVender,
            'razonNoVender'      => $razonNoVender,
            // Multimoneda: monedas disponibles y TC del día (soles por 1 USD).
            'monedas'            => ['PEN', 'USD'],
            'tipoCambioHoy'      => $this->tipoCambioHoy(),
            // Facturación electrónica: el POS necesita saber si emite de verdad,
            // con qué serie y —sobre todo— si el emisor apunta a SUNAT producción.
            'facturacion'        => $this->configFacturacionPos(),
        ]);
    }

    /**
     * Configuración de facturación electrónica que consume el POS.
     *
     * TODO lo fiscal —modo, umbral de boleta identificada, tasa, series— sale de
     * `GET /api/v1/configuracion`, que es la fuente única de verdad del contrato
     * (§7). Aquí no se decide nada: se pregunta.
     *
     * ANTES esto leía `config('facturamac.umbral_boleta_identificada')` y
     * `config('facturamac.series')`, claves que ya NO existen en el
     * `config/facturamac.php` adelgazado, así que el POS se quedaba con el umbral
     * 700 pasara lo que pasara y con `series => []` — es decir, sin poder mostrar
     * nunca en caja con qué serie se iba a emitir. Y el aviso de producción salía
     * de `/ping` + `sunat_beta`, que no distingue `simulacion` de `produccion`.
     *
     * El FAIL-SAFE se conserva íntegro, ahora dentro de ConfiguracionFacturacion:
     * si el emisor no responde, `produccion` es `true` y la cajera ve el aviso
     * rojo. Un aviso de más no cuesta nada; uno de menos cuesta una Nota de
     * Crédito (ya pasó: ver AUDITORIA_PRODUCCION_SUNAT.md).
     */
    private function configFacturacionPos(): array
    {
        return app(\App\Services\Facturacion\ConfiguracionFacturacion::class)->paraPos();
    }

    /**
     * ¿Se puede consultar `venta_comprobantes` sin riesgo de reventar la pantalla?
     *
     * POR QUÉ EXISTE ESTA GUARDA: la facturación electrónica es un módulo OPCIONAL
     * que se despliega por partes, y `enabled` es `false` por defecto. En una
     * instalación con el módulo apagado y la migración sin aplicar, un `with()`
     * sobre la relación tira un "relation venta_comprobantes does not exist" y
     * devuelve 500 en `/ventas` — la pantalla más usada del POS— por un módulo que
     * se supone inerte. Esta forma exacta de fallo ya tumbó el sistema una vez.
     *
     * Mismo criterio que VentaService::bloquearSiTieneComprobanteEmitido(),
     * DevolucionService::encolarNotaCredito() y TicketPrintService.
     *
     * Se memoiza en la INSTANCIA (no en un `static`) a propósito: el controlador se
     * resuelve una vez por request, así que basta una comprobación por request y
     * ningún test se contamina con el resultado del anterior.
     */
    private ?bool $cpeDisponible = null;

    private function comprobantesDisponibles(): bool
    {
        if ($this->cpeDisponible !== null) {
            return $this->cpeDisponible;
        }

        // Interruptor de despliegue: apagado no se consulta nada, ni aunque la
        // tabla exista. Es lo que hace que el módulo sea de verdad inerte.
        if (! config('facturamac.enabled')) {
            return $this->cpeDisponible = false;
        }

        if (! method_exists(Venta::class, 'comprobanteElectronico')) {
            return $this->cpeDisponible = false;
        }

        try {
            return $this->cpeDisponible = \Illuminate\Support\Facades\Schema::hasTable('venta_comprobantes');
        } catch (\Throwable $e) {
            // Encendido pero sin migrar (o sin poder comprobarlo): la lista de
            // ventas se pinta igual, solo que sin la columna del comprobante.
            return $this->cpeDisponible = false;
        }
    }

    // ── Ventas (historial) ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = $request->user();
        $esAdmin = $user->rol->es_admin;

        // La cajera (no admin) se limita a SUS ventas y por defecto a las de HOY.
        // El admin ve todo el historial de la empresa/local. Si la cajera cambia
        // el rango de fechas, sigue viendo solo lo suyo.
        $hoy         = now()->toDateString();
        $fechaDesde  = $request->fecha_desde ?: (!$esAdmin ? $hoy : null);
        $fechaHasta  = $request->fecha_hasta ?: (!$esAdmin ? $hoy : null);

        $q = trim((string) $request->input('q', ''));

        // Con el módulo apagado (o sin migrar) NO se toca `venta_comprobantes`:
        // `/ventas` es la pantalla más usada del POS y no puede caerse por un
        // módulo opcional. Ver comprobantesDisponibles().
        $conCpe = $this->comprobantesDisponibles();

        $ventas = Venta::deEmpresa($user->empresa_id)
            ->with(array_merge(
                ['user', 'cliente', 'local', 'caja', 'turno',
                 'pagos:id,venta_id,metodo_pago_id,monto',
                 'pagos.metodoPago:id,nombre'],
                // Estado del CPE en la lista. Con `with` es UNA consulta por
                // página (whereIn sobre las 25 ventas), no una por fila; y se
                // piden solo las columnas que pinta la tabla. Las ventas
                // `ticket` simplemente no tienen fila y llegan con null.
                $conCpe ? ['comprobanteElectronico:id,venta_id,tipo,numero,estado'] : [],
            ))
            ->when(!$esAdmin, fn($qq) => $qq->where('user_id', $user->id))
            ->when($request->estado, fn($qq, $v) => $qq->where('estado', $v))
            ->when($fechaDesde, fn($qq, $v) => $qq->where('fecha_venta', '>=', $v))
            ->when($fechaHasta, fn($qq, $v) => $qq->where('fecha_venta', '<=', $v . ' 23:59:59'))
            ->when($request->local_id, fn($qq, $v) => $qq->where('local_id', $v))
            ->when($request->turno_id, fn($qq, $v) => $qq->where('turno_id', $v))
            ->when($user->local_id, fn($qq) => $qq->where('local_id', $user->local_id))
            // Búsqueda server-side: N° de venta, cliente (nombre/razón/doc) y monto.
            // Se agrupa en un where anidado para no romper los filtros de arriba.
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('numero', 'ilike', "%{$q}%")
                      ->orWhereHas('cliente', function ($c) use ($q) {
                          $c->where('nombres', 'ilike', "%{$q}%")
                            ->orWhere('apellidos', 'ilike', "%{$q}%")
                            ->orWhere('razon_social', 'ilike', "%{$q}%")
                            ->orWhere('numero_documento', 'ilike', "%{$q}%");
                      });
                    if (is_numeric($q)) {
                        $w->orWhere('total', $q);
                    }
                });
            })
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // El comprobante viaja aplanado a lo que la lista realmente muestra
        // (número, tipo, estado y si el estado ya es final). Se envía el modelo
        // recortado en vez del completo para no arrastrar hash, QR ni la
        // respuesta de SUNAT en cada una de las 25 filas.
        // OJO: `$venta->comprobanteElectronico` carga la relación PEREZOSAMENTE si
        // no vino en el `with`. Sin el `$conCpe` de aquí, la guarda de arriba no
        // serviría de nada: la consulta a `venta_comprobantes` se colaría igual,
        // y encima una por fila.
        $ventas->getCollection()->each(function (Venta $venta) use ($conCpe): void {
            $ce = $conCpe ? $venta->comprobanteElectronico : null;
            $venta->unsetRelation('comprobanteElectronico');
            $venta->setAttribute('comprobante_electronico', $ce ? [
                'numero' => $ce->numero,
                'tipo'   => $ce->tipo,
                'estado' => $ce->estado,
                // Lo decide el modelo (ESTADOS_TERMINALES): la lista no debe
                // tener su propia copia de esa lista.
                'final'  => $ce->esFinal(),
            ] : null);
        });

        $locales = $this->scope->localesVisibles($user);

        // Turno a resumir en las cards: el filtrado explícitamente, o el turno
        // abierto del usuario (cajera) / el más reciente abierto en su ámbito (admin).
        $turnosResumen = $this->resolverTurnosResumen($user, $esAdmin, $request);

        return Inertia::render('Ventas/Index', [
            'ventas'  => $ventas,
            'locales' => $locales,
            'esAdmin' => $esAdmin,
            'turnos'  => $this->turnosParaFiltro($user, $esAdmin, $request, $fechaDesde, $fechaHasta),
            'resumen' => $turnosResumen->isNotEmpty() ? $this->resumenDeTurnos($turnosResumen) : null,
            // Reflejar en la UI las fechas efectivas (incluye el default de hoy).
            'filters' => array_merge(
                $request->only(['estado', 'fecha_desde', 'fecha_hasta', 'local_id', 'turno_id', 'q']),
                ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta],
            ),
        ]);
    }

    /** Turnos que alimentan el selector de filtro (y contexto de las cards). */
    private function turnosParaFiltro(User $user, bool $esAdmin, Request $request, ?string $fechaDesde, ?string $fechaHasta)
    {
        return Turno::deEmpresa($user->empresa_id)
            ->with(['user:id,name', 'caja:id,nombre'])
            ->when(!$esAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->when($request->local_id, fn($q, $v) => $q->where('local_id', $v))
            ->when($fechaDesde, fn($q, $v) => $q->whereDate('fecha_apertura', '>=', $v))
            ->when($fechaHasta, fn($q, $v) => $q->whereDate('fecha_apertura', '<=', $v))
            ->orderByDesc('fecha_apertura')
            ->limit(60)
            ->get(['id', 'user_id', 'caja_id', 'local_id', 'fecha_apertura', 'estado']);
    }

    /** Resuelve qué turno resumir en las cards. */
    private function resolverTurnosResumen(User $user, bool $esAdmin, Request $request): \Illuminate\Support\Collection
    {
        $base = Turno::deEmpresa($user->empresa_id)->with(['user:id,name', 'caja:id,nombre']);

        // Turno específico (filtro ?turno_id): solo ese.
        if ($request->turno_id) {
            $t = $base->find($request->turno_id);
            return $t ? collect([$t]) : collect();
        }

        // Sin filtro: TODAS las cajas abiertas. Así el ADMIN ve el efectivo total
        // de todos los turnos abiertos (antes salía solo el último); la cajera
        // sigue viendo solo el suyo.
        return $base->where('estado', 'abierto')
            ->when(!$esAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($user->local_id, fn($q) => $q->where('local_id', $user->local_id))
            ->orderByDesc('fecha_apertura')
            ->get();
    }

    /**
     * Resumen de caja del turno para las cards. Una sola consulta agregada
     * (groupBy método + cuenta) sobre venta_pagos; nada de iterar todas las
     * ventas en PHP. Espejo del desglose del cierre de turno.
     */
    private function resumenDeTurnos(\Illuminate\Support\Collection $turnos): array
    {
        // Uno o VARIOS turnos: cuando un ADMIN entra a Ventas sin filtrar un turno,
        // se agregan TODAS las cajas abiertas (antes salía solo la última). Los
        // montos suman sobre el conjunto; $turno es la referencia del encabezado.
        $turnoIds = $turnos->pluck('id')->all();
        $turno    = $turnos->first();

        // Cuenta por defecto por método: cuando el método tiene UNA sola cuenta,
        // la usamos para los pagos que no registraron cuenta (cuenta_metodo_pago_id
        // null). Así "Yape" deja de salir como "sin cuenta" cuando es inequívoco.
        $cuentaDefault = DB::table('cuenta_metodo_pago as cmp')
            ->join('cuentas as c', 'c.id', '=', 'cmp.cuenta_id')
            ->selectRaw('cmp.metodo_pago_id, MIN(c.nombre) as nombre, MIN(c.banco) as banco')
            ->groupBy('cmp.metodo_pago_id')
            ->havingRaw('COUNT(*) = 1')
            ->get()->keyBy('metodo_pago_id');

        // Desglose por método + cuenta. La cuenta real se resuelve VÍA EL PIVOTE:
        // venta_pagos.cuenta_metodo_pago_id → cuenta_metodo_pago.id → cuenta_id →
        // cuentas.id. (La relación cuentaMetodoPago del modelo apunta directo a
        // cuentas y por eso salía "sin cuenta" en Yape/transferencias.)
        $filas = \App\Models\VentaPago::query()
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->leftJoin('cuenta_metodo_pago as cmp', 'cmp.id', '=', 'venta_pagos.cuenta_metodo_pago_id')
            ->leftJoin('cuentas as c', 'c.id', '=', 'cmp.cuenta_id')
            ->leftJoin('metodos_pago as mp', 'mp.id', '=', 'venta_pagos.metodo_pago_id')
            ->leftJoin('tipos_metodo_pago as tmp', 'tmp.id', '=', 'mp.tipo_id')
            ->whereIn('ventas.turno_id', $turnoIds)
            ->where('ventas.estado', 'completada')
            ->groupBy('mp.id', 'mp.nombre', 'tmp.slug', 'c.nombre', 'c.banco')
            ->selectRaw('mp.id as metodo_id, mp.nombre as metodo, tmp.slug as slug, c.nombre as cuenta, c.banco as banco, SUM(venta_pagos.monto) as total')
            ->get();

        // Consolidamos por MÉTODO (una card por método: Yape, Transferencia...);
        // las cuentas quedan como detalle anidado. Antes se hacía una card por
        // método+cuenta y salían p.ej. dos "Yape" (una por cada cuenta), confuso.
        $porMetodo = [];
        foreach ($filas->concat($this->abonosPorMetodo($turnoIds)) as $f) {
            [$cuenta, $banco] = $this->resolverCuentaMetodo($f, $cuentaDefault);
            $metodo = $f->metodo ?? 'Otro';
            $monto  = (float) $f->total;
            if (!isset($porMetodo[$metodo])) {
                $porMetodo[$metodo] = [
                    'metodo'      => $metodo,
                    'slug'        => $f->slug,
                    'es_efectivo' => ($f->slug === 'efectivo'),
                    'total'       => 0.0,
                    'cuentas'     => [],
                ];
            }
            $porMetodo[$metodo]['total'] += $monto;
            $ckey = $cuenta ?? '—';
            if (!isset($porMetodo[$metodo]['cuentas'][$ckey])) {
                $porMetodo[$metodo]['cuentas'][$ckey] = ['cuenta' => $cuenta, 'banco' => $banco, 'total' => 0.0];
            }
            $porMetodo[$metodo]['cuentas'][$ckey]['total'] += $monto;
        }

        $metodos = array_map(function ($m) {
            $m['total']   = round($m['total'], 2);
            $m['cuentas'] = array_map(function ($c) {
                $c['total'] = round($c['total'], 2);
                return $c;
            }, array_values($m['cuentas']));
            usort($m['cuentas'], fn($a, $b) => $b['total'] <=> $a['total']);
            return $m;
        }, array_values($porMetodo));
        usort($metodos, fn($a, $b) => $b['total'] <=> $a['total']);

        // Efectivo separado en ventas vs abonos (para el detalle de la card de caja).
        $efectivoVentas = (float) \App\Models\VentaPago::whereHas('venta', fn($q) =>
            $q->whereIn('turno_id', $turnoIds)->where('estado', 'completada')
        )->whereHas('metodoPago.tipo', fn($q) => $q->where('slug', 'efectivo'))->sum('monto');
        $efectivoAbonos = (float) \App\Models\VentaAbono::whereIn('turno_id', $turnoIds)
            ->whereHas('metodoPago.tipo', fn($q) => $q->where('slug', 'efectivo'))->sum('monto');

        // Anticipos de clientes recibidos EN ESTE TURNO (dinero que entró a caja,
        // aparte de ventas y abonos). Solo el efectivo suma al esperado del cajón
        // (mismo criterio que Turno::calcularMontoEsperado). Solo los anulados se
        // excluyen (su ingreso fue revertido); los devueltos sí entraron aquí y su
        // egreso se descuenta en la caja por donde salió (ver $devolucionAnticipos).
        $anticiposBase = \App\Models\ClienteAnticipo::whereIn('turno_id', $turnoIds)
            ->where('estado', '<>', 'anulado');
        $anticiposEfectivo = (float) (clone $anticiposBase)
            ->where(fn($q) =>
                $q->whereHas('metodoPago.tipo', fn($t) => $t->where('slug', 'efectivo'))
                  ->orWhere(fn($q2) => $q2->whereNull('metodo_pago_id')
                        ->whereHas('cuenta', fn($c) => $c->where('es_efectivo', true))))
            ->sum('monto');

        $gastos = (float) \App\Models\Gasto::whereIn('turno_id', $turnoIds)->sum('monto');

        // Reconciliación con el crédito: lo cobrado por métodos (arriba) NO cubre
        // las ventas a cuenta por cobrar. total_vendido = cobrado + por_cobrar.
        // Sin esto, a la cajera le "faltaba" el monto de las ventas al crédito.
        $ventasTurno  = Venta::whereIn('turno_id', $turnoIds)->where('estado', 'completada');
        $totalVendido = (float) (clone $ventasTurno)->sum('total');
        $porCobrar    = (float) (clone $ventasTurno)->sum('saldo_pendiente');
        // cobrado = lo aplicado a las ventas (monto_pagado), no la suma bruta de
        // pagos (que incluye vuelto). Así cuadra exacto: total = cobrado + por_cobrar.
        $cobrado      = (float) (clone $ventasTurno)->sum('monto_pagado');

        // Abonos a cuentas por cobrar recibidos en este turno (dinero extra que
        // entró a caja, aparte de las ventas del turno).
        $abonos = (float) \App\Models\VentaAbono::whereIn('turno_id', $turnoIds)->sum('monto');

        // Reembolsos de devoluciones en efectivo (descuentan la caja del turno).
        $reembolsos = (float) \App\Models\DevolucionPago::whereHas('devolucion', fn($q) =>
            $q->whereIn('turno_id', $turnoIds)->whereIn('estado', ['aprobada', 'completada'])
        )->whereHas('metodoPago.tipo', fn($q) => $q->where('slug', 'efectivo'))->sum('monto');

        // Detalle para el modal "ver detalle de caja" (corroboración).
        $abonosDetalle = \App\Models\VentaAbono::whereIn('turno_id', $turnoIds)
            ->with(['venta.cliente', 'metodoPago'])
            ->orderByDesc('id')->get()
            ->map(fn ($a) => [
                'cliente' => $a->venta?->cliente
                    ? ($a->venta->cliente->razon_social
                        ?? trim(($a->venta->cliente->nombres ?? '') . ' ' . ($a->venta->cliente->apellidos ?? '')))
                    : 'General',
                'venta'  => $a->venta?->numero,
                'metodo' => $a->metodoPago?->nombre ?? '—',
                'monto'  => round((float) $a->monto, 2),
            ]);

        // Detalle de anticipos del turno para el modal (todos los métodos; el que
        // no es efectivo se muestra igual, pero no suma al efectivo esperado).
        $anticiposDetalle = (clone $anticiposBase)
            ->with(['cliente', 'metodoPago'])
            ->orderByDesc('id')->get()
            ->map(fn ($a) => [
                'cliente' => $a->cliente
                    ? ($a->cliente->es_cliente_general
                        ? 'Clientes varios'
                        : ($a->cliente->razon_social
                            ?? trim(($a->cliente->nombres ?? '') . ' ' . ($a->cliente->apellidos ?? ''))))
                    : '—',
                'metodo' => $a->metodoPago?->nombre ?? 'Efectivo',
                'monto'  => round((float) $a->monto, 2),
            ]);

        $gastosDetalle = \App\Models\Gasto::whereIn('turno_id', $turnoIds)
            ->with('concepto')->orderByDesc('id')->get()
            ->map(fn ($g) => [
                'concepto' => $g->concepto?->nombre ?? $g->comentario ?? 'Gasto',
                'monto'    => round((float) $g->monto, 2),
            ]);

        // Pagos de entradas (compras) en EFECTIVO desde esta caja (salen del cajón).
        $comprasQuery = \App\Models\EntradaPago::whereIn('turno_id', $turnoIds)
            ->where(fn($q) =>
                $q->whereHas('metodoPago.tipo', fn($t) => $t->where('slug', 'efectivo'))
                  ->orWhere(fn($q2) => $q2->whereNull('metodo_pago_id')
                        ->whereHas('cuenta', fn($c) => $c->where('es_efectivo', true))));
        $compras = (float) (clone $comprasQuery)->sum('monto');
        $comprasDetalle = (clone $comprasQuery)->with('entrada')->orderByDesc('id')->get()
            ->map(fn ($p) => [
                'proveedor' => $p->entrada?->proveedor ?? 'Compra',
                'documento' => $p->entrada?->numero_documento,
                'monto'     => round((float) $p->monto, 2),
            ]);

        // Deuda / préstamos (módulo "Afecta caja"): desembolsos y cuotas en
        // EFECTIVO imputados a estas cajas. Igual que Turno::calcularMontoEsperado,
        // la efectividad y el signo se leen del movimiento de tesorería (ingreso
        // entra al cajón, egreso sale). Es lo que hace que el desglose cuadre con
        // el total 'efectivo_en_caja' cuando hay pagos de deuda.
        $deudaEfectivo = function (string $tipoMov) use ($turnoIds, $turno) {
            return (float) \App\Models\CuentaMovimiento::where('empresa_id', $turno->empresa_id)
                ->where('tipo', $tipoMov)
                ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
                ->where(function ($q) use ($turnoIds) {
                    $q->where(fn ($s) => $s->where('ref_tipo', 'deuda')
                            ->whereIn('ref_id', \App\Models\Deuda::whereIn('turno_id', $turnoIds)->select('id')))
                      ->orWhere(fn ($s) => $s->where('ref_tipo', 'deuda_pago')
                            ->whereIn('ref_id', \App\Models\DeudaPago::whereIn('turno_id', $turnoIds)->select('id')));
                })
                ->sum('monto');
        };
        $deudaIngreso = $deudaEfectivo('ingreso');
        $deudaEgreso  = $deudaEfectivo('egreso');

        $deudaDetalle = \App\Models\CuentaMovimiento::where('empresa_id', $turno->empresa_id)
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->where(function ($q) use ($turnoIds) {
                $q->where(fn ($s) => $s->where('ref_tipo', 'deuda')
                        ->whereIn('ref_id', \App\Models\Deuda::whereIn('turno_id', $turnoIds)->select('id')))
                  ->orWhere(fn ($s) => $s->where('ref_tipo', 'deuda_pago')
                        ->whereIn('ref_id', \App\Models\DeudaPago::whereIn('turno_id', $turnoIds)->select('id')));
            })
            ->orderByDesc('id')->get()
            ->map(fn ($m) => [
                'descripcion' => $m->descripcion,
                'tipo'        => $m->tipo,   // ingreso | egreso
                'monto'       => round((float) $m->monto, 2),
            ]);

        // Devoluciones de anticipo pagadas EN EFECTIVO desde estas cajas: el
        // billete sale del cajón. Espejo exacto de Turno::calcularMontoEsperado —
        // la caja se DERIVA del movimiento de tesorería (quién lo registró y en
        // qué momento real), porque el anticipo solo sabe en qué turno ENTRÓ el
        // dinero, que casi nunca es el mismo por el que sale.
        $devolucionAnticipoMovs = \App\Models\CuentaMovimiento::where('empresa_id', $turno->empresa_id)
            ->where('tipo', 'egreso')
            ->where('ref_tipo', 'cliente_anticipo_devolucion')
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->where(function ($q) use ($turnos) {
                foreach ($turnos as $t) {
                    $q->orWhere(fn ($s) => $s
                        ->where('user_id', $t->user_id)
                        ->where('created_at', '>=', $t->fecha_apertura)
                        ->when($t->fecha_cierre, fn ($w) => $w->where('created_at', '<=', $t->fecha_cierre)));
                }
            })
            ->orderByDesc('id')->get();

        $devolucionAnticipos = (float) $devolucionAnticipoMovs->sum('monto');

        // Nombre del cliente para el modal: el movimiento apunta al anticipo por ref_id.
        $anticiposDevueltos = \App\Models\ClienteAnticipo::whereIn('id', $devolucionAnticipoMovs->pluck('ref_id')->filter())
            ->with('cliente')->get()->keyBy('id');

        $devolucionAnticiposDetalle = $devolucionAnticipoMovs->map(function ($m) use ($anticiposDevueltos) {
            $cli = $anticiposDevueltos->get($m->ref_id)?->cliente;
            return [
                'anticipo' => (int) $m->ref_id,
                'cliente'  => $cli
                    ? ($cli->es_cliente_general
                        ? 'Clientes varios'
                        : ($cli->razon_social ?? trim(($cli->nombres ?? '') . ' ' . ($cli->apellidos ?? ''))))
                    : '—',
                'monto'    => round((float) $m->monto, 2),
            ];
        })->values();

        $variosTurnos = count($turnoIds) > 1;
        return [
            'turno' => [
                'id'     => $variosTurnos ? 0 : $turno->id,
                'fecha'  => $turno->fecha_apertura,
                'cajera' => $variosTurnos ? null : $turno->user?->name,
                'caja'   => $variosTurnos ? ('Todas las cajas abiertas (' . count($turnoIds) . ')') : $turno->caja?->nombre,
                'estado' => $variosTurnos ? 'abierto' : $turno->estado,
            ],
            'metodos'          => $metodos,
            'cobrado'          => round($cobrado, 2),         // aplicado a ventas (monto_pagado)
            'total_efectivo'   => round($efectivoVentas + $efectivoAbonos, 2),
            'efectivo_ventas'  => round($efectivoVentas, 2),
            'efectivo_abonos'  => round($efectivoAbonos, 2),
            'anticipos_efectivo' => round($anticiposEfectivo, 2), // anticipos en efectivo (entran a caja)
            'total_vendido'    => round($totalVendido, 2),    // cobrado + por cobrar
            'por_cobrar'       => round($porCobrar, 2),       // crédito pendiente del turno
            'abonos'           => round($abonos, 2),          // cobros de crédito recibidos en el turno
            'gastos'           => round($gastos, 2),
            'reembolsos'       => round($reembolsos, 2),      // devoluciones en efectivo
            'compras'          => round($compras, 2),         // pagos de entradas en efectivo (salen de caja)
            'deuda_ingreso'    => round($deudaIngreso, 2),    // desembolsos/cobros de deuda en efectivo (entran)
            'deuda_egreso'     => round($deudaEgreso, 2),     // cuotas/préstamos de deuda en efectivo (salen)
            'devolucion_anticipos' => round($devolucionAnticipos, 2), // devoluciones de anticipo en efectivo (salen)
            'apertura'         => round((float) $turnos->sum('monto_apertura'), 2),
            // Efectivo real esperado, SUMADO sobre todas las cajas del conjunto
            // (apertura + ventas + abonos − gastos − reembolsos − compras).
            'efectivo_en_caja' => round((float) $turnos->sum(fn ($t) => $t->calcularMontoEsperado()), 2),
            'abonos_detalle'   => $abonosDetalle,
            'anticipos_detalle'=> $anticiposDetalle,
            'gastos_detalle'   => $gastosDetalle,
            'compras_detalle'  => $comprasDetalle,
            'deuda_detalle'    => $deudaDetalle,
            'devolucion_anticipos_detalle' => $devolucionAnticiposDetalle,
        ];
    }

    /** Resuelve [cuenta, banco] de una fila de cobro, con fallback a la cuenta por defecto del método. */
    private function resolverCuentaMetodo(object $f, $cuentaDefault): array
    {
        if ($f->cuenta !== null) {
            return [$f->cuenta, $f->banco];
        }
        $def = $cuentaDefault[$f->metodo_id] ?? null;
        return [$def->nombre ?? null, $def->banco ?? null];
    }

    /**
     * Abonos a cuentas por cobrar cobrados EN ESTE TURNO, agrupados por
     * método + cuenta (misma forma que las ventas). Los abonos guardan la cuenta
     * DIRECTA en venta_abonos.cuenta_id (no usan el pivote de las ventas).
     */
    private function abonosPorMetodo(array $turnoIds)
    {
        return \App\Models\VentaAbono::query()
            ->from('venta_abonos as va')
            ->leftJoin('metodos_pago as mp', 'mp.id', '=', 'va.metodo_pago_id')
            ->leftJoin('tipos_metodo_pago as tmp', 'tmp.id', '=', 'mp.tipo_id')
            ->leftJoin('cuentas as c', 'c.id', '=', 'va.cuenta_id')
            ->whereIn('va.turno_id', $turnoIds)
            ->groupBy('mp.id', 'mp.nombre', 'tmp.slug', 'c.nombre', 'c.banco')
            ->selectRaw('mp.id as metodo_id, mp.nombre as metodo, tmp.slug as slug, c.nombre as cuenta, c.banco as banco, SUM(va.monto) as total')
            ->get();
    }

    /**
     * Historial de precios de venta por cliente (JSON para el POS). Devuelve, por
     * producto, los últimos precios a los que se le vendió a ESE cliente. Una sola
     * consulta con window function (últimas 3 líneas por producto + total de veces).
     */
    public function historialPreciosCliente(Request $request)
    {
        $user      = $request->user();
        $clienteId = (int) $request->query('cliente_id');
        if (!$clienteId) {
            return response()->json([]);
        }

        $sub = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->where('v.empresa_id', $user->empresa_id)
            ->where('v.cliente_id', $clienteId)
            ->where('v.estado', 'completada')
            ->selectRaw('vi.producto_id, vi.precio_unitario, vi.unidad_nombre, vi.cantidad, v.fecha_venta,
                row_number() over (partition by vi.producto_id order by v.fecha_venta desc, v.id desc) as rn,
                count(*) over (partition by vi.producto_id) as veces');

        $rows = DB::query()->fromSub($sub, 't')
            ->where('rn', '<=', 3)
            ->orderBy('producto_id')->orderBy('rn')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            if (!isset($map[$r->producto_id])) {
                $map[$r->producto_id] = [
                    'ultimo_precio' => (float) $r->precio_unitario,
                    'ultima_fecha'  => $r->fecha_venta,
                    'veces'         => (int) $r->veces,
                    'historial'     => [],
                ];
            }
            $map[$r->producto_id]['historial'][] = [
                'fecha'    => $r->fecha_venta,
                'precio'   => (float) $r->precio_unitario,
                'cantidad' => (float) $r->cantidad,
                'unidad'   => $r->unidad_nombre,
            ];
        }

        return response()->json($map);
    }

    /** Payload de impresión de una venta (para imprimir desde la lista sin abrir el detalle). */
    public function ticket(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        return response()->json(app(TicketPrintService::class)->payloadDeVenta($venta));
    }

    public function show(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $venta->load([
            'user', 'cliente', 'local', 'caja', 'turno',
            'items.producto', 'items.productoUnidad.unidadMedida', 'items.descuentoConcepto',
            'pagos.metodoPago', 'pagos.cuentaMetodoPago',
            'descuentosLog.concepto', 'descuentosLog.user',
        ]);

        return Inertia::render('Ventas/Show', [
            'venta' => $venta,
            // Payload listo para el agente local de impresión (VentoryPrint.exe).
            'ticketImpresion' => app(TicketPrintService::class)->payloadDeVenta($venta),
        ]);
    }

    // ── Update (edición completa dentro de los 3 min) ───────────────────────────

    public function update(StoreVentaRequest $request, Venta $venta)
    {
        $user = $request->user();
        abort_if($venta->empresa_id !== $user->empresa_id, 403);

        if ($venta->estado !== 'completada') {
            return back()->withErrors(['venta' => 'Solo se pueden editar ventas completadas.']);
        }
        // El admin edita sin límite de tiempo. La cajera, solo dentro de los 3 min.
        if (!$user->rol->es_admin && !$this->dentroPlazoEdicion($venta)) {
            return back()->withErrors(['venta' => 'El plazo para editar esta venta (3 minutos) ya venció. Si necesitas corregirla, anúlala.']);
        }

        $venta = $this->ventaService->actualizar($venta, $request->validated(), $user);

        // V12 — La edición puede cambiar el tipo de comprobante (ticket →
        // boleta/factura) o el detalle de uno aún no emitido. Llegar aquí ya
        // implica que la venta NO tenía comprobante informado a SUNAT: la
        // guarda de VentaService::actualizar() aborta ese caso (solo cabe Nota
        // de Crédito). Aun así el job revalida con esEmitido() antes de emitir.
        if ($venta->tipo_comprobante !== 'ticket') {
            EmitirComprobanteElectronico::dispatch($venta)->afterCommit();
        }

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Venta {$venta->numero} actualizada correctamente.")
            // Auto-imprime solo si usó "Crear e imprimir" (mismo criterio que store).
            ->with('imprimir_ticket', $request->boolean('imprimir'));
    }

    // ── Store (POS: registrar venta) ───────────────────────────────────────────

    public function store(StoreVentaRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Backdate de admin: registrar la venta en un turno abierto específico
        // (típicamente uno REABIERTO de otro día/cajera) con la fecha real de
        // la operación. Solo admins; la fecha se acota al rango [apertura del
        // turno, hoy] para que no se pueda inventar cualquier día.
        $turno = null;
        if (!empty($data['turno_id']) && $user->rol->es_admin) {
            $turno = Turno::where('id', $data['turno_id'])
                ->where('empresa_id', $user->empresa_id)
                ->where('estado', 'abierto')
                ->first();
            if (!$turno) {
                return back()->withErrors(['turno' => 'El turno indicado no está abierto.']);
            }

            $fechaVenta = $data['fecha_venta'] ?? $turno->fecha_apertura?->toDateString();
            $minFecha   = $turno->fecha_apertura?->toDateString();
            if ($fechaVenta && $minFecha && ($fechaVenta < $minFecha || $fechaVenta > now()->toDateString())) {
                return back()->withErrors(['fecha_venta' => "La fecha de la venta debe estar entre la apertura del turno ({$minFecha}) y hoy."]);
            }
            $data['fecha_venta'] = $fechaVenta;
        } else {
            // Flujo normal: turno propio activo, fecha = ahora (sin backdate).
            unset($data['fecha_venta']);
            $turno = Turno::turnoActivoDelUsuario($user->id);
        }

        if (!$turno) {
            return back()->withErrors(['turno' => 'No tienes un turno activo.']);
        }

        $venta = $this->ventaService->crear($data, $user, $turno);

        // Si la venta vino desde una cita prellenada, vincular y marcar la cita
        // como completada. Falla silenciosamente si la cita no existe / no aplica.
        if ($citaId = $request->input('cita_id')) {
            $cita = \App\Models\Cita::where('id', $citaId)
                ->where('empresa_id', $user->empresa_id)
                ->first();
            if ($cita && !$cita->venta_id && $cita->estaActiva()) {
                try {
                    $this->citaService->vincularVenta($cita, $venta, $user);
                } catch (\Throwable $e) {
                    \Log::warning('No se pudo vincular cita con venta', [
                        'cita_id'  => $citaId,
                        'venta_id' => $venta->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        // Si la venta vino desde una cotización prellenada, vincular y marcar
        // la cotización como convertida (precio cotizado honrado en el POS).
        if ($cotizacionId = $request->input('cotizacion_id')) {
            $cotizacion = \App\Models\Cotizacion::where('id', $cotizacionId)
                ->where('empresa_id', $user->empresa_id)
                ->first();
            if ($cotizacion && $cotizacion->esConvertible()) {
                $cotizacion->update([
                    'venta_id' => $venta->id,
                    'estado'   => \App\Models\Cotizacion::ESTADO_CONVERTIDA,
                ]);
                \App\Services\AuditoriaService::log('cotizacion.convertida', $cotizacion, [
                    'numero'       => $cotizacion->numero,
                    'venta_id'     => $venta->id,
                    'numero_venta' => $venta->numero,
                    'total_venta'  => (float) $venta->total,
                ], $user);
            }
        }

        // V12 — Facturación electrónica. La venta YA está cerrada y cuadrada en
        // caja; la emisión ante SUNAT va aparte y en segundo plano: si SUNAT
        // tarda o está caído, el cajero no puede quedarse esperando con la cola
        // de clientes delante. `afterCommit` garantiza que el worker no lea la
        // venta antes de que exista. El `ticket` es nota interna: no se emite.
        if ($venta->tipo_comprobante !== 'ticket') {
            EmitirComprobanteElectronico::dispatch($venta)->afterCommit();
        }

        return redirect()->route('ventas.show', $venta)
            ->with('success', "Venta {$venta->numero} registrada correctamente.")
            // Flag one-shot: el Show auto-imprime el ticket UNA sola vez tras
            // registrar la venta (no al volver a abrir la venta después). Solo si
            // el cajero usó el botón "Crear e imprimir"; con "Confirmar venta" no.
            ->with('imprimir_ticket', $request->boolean('imprimir'));
    }

    // ── Anular ─────────────────────────────────────────────────────────────────

    public function anular(AnularVentaRequest $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        if ($venta->estado === 'anulada') {
            return back()->withErrors(['venta' => 'La venta ya está anulada.']);
        }

        // El admin anula sin restricción. Para la cajera, pasados 3 min anular
        // exige código de autorización de un administrador. (El envío del código
        // por WhatsApp/Telegram se implementará después; por ahora el código es
        // la clave de un admin activo de la empresa.)
        if (!$request->user()->rol->es_admin && !$this->dentroPlazoEdicion($venta)) {
            $codigo = (string) $request->input('codigo_autorizacion', '');
            if (!$this->codigoAdminValido($request->user()->empresa_id, $codigo)) {
                return back()->withErrors([
                    'codigo_autorizacion' => 'Pasaron más de 3 minutos: anular requiere el código de autorización de un administrador.',
                ]);
            }
        }

        $this->ventaService->anular(
            $venta,
            $request->user(),
            $request->validated('motivo'),
        );

        return redirect()->back()->with('success', "Venta {$venta->numero} anulada correctamente.");
    }
}
