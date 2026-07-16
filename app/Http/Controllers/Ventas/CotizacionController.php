<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Services\AuditoriaService;
use App\Services\TicketPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Cotizaciones (proformas): precios congelados con vigencia y seguimiento
 * comercial (contactos, aceptación/rechazo). Se convierten en venta desde el
 * POS (?cotizacion_id=), patrón idéntico al de las citas prellenadas.
 */
class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $hoy  = now()->toDateString();

        // Marcado automático de vencidas al listar (scheduler-friendly, sin
        // cron): las vigentes cuya vigencia ya pasó dejan de ofrecer precios.
        Cotizacion::deEmpresa($user->empresa_id)
            ->vigente()
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->update(['estado' => Cotizacion::ESTADO_VENCIDA, 'updated_at' => now()]);

        $estado = $request->input('estado', 'vigentes'); // vigentes|alerta|todas
        $q      = trim((string) $request->input('q', ''));
        $limite = now()->addDays(3)->toDateString();

        $cotizaciones = Cotizacion::deEmpresa($user->empresa_id)
            ->with(['cliente', 'user:id,name', 'venta:id,numero', 'items'])
            // Tab "Vigentes": lo que aún está en juego (vigentes + aceptadas sin cobrar).
            ->when($estado === 'vigentes', fn ($qq) => $qq->whereIn('estado', [
                Cotizacion::ESTADO_VIGENTE, Cotizacion::ESTADO_ACEPTADA,
            ]))
            // Tab/filtro "alerta": por vencer (≤3 días) + vencidas sin respuesta.
            ->when($estado === 'alerta', fn ($qq) => $qq->where(function ($w) use ($hoy, $limite) {
                $w->where(fn ($v) => $v->vigente()->whereBetween('fecha_vencimiento', [$hoy, $limite]))
                  ->orWhere(fn ($v) => $v->where('estado', Cotizacion::ESTADO_VENCIDA)
                      ->where(fn ($c) => $c->whereNull('ultimo_contacto')
                          ->orWhereColumn('ultimo_contacto', '<', 'fecha_vencimiento')));
            }))
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('numero', 'ilike', "%{$q}%")
                  ->orWhere('referencia', 'ilike', "%{$q}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nombres', 'ilike', "%{$q}%")
                      ->orWhere('apellidos', 'ilike', "%{$q}%")
                      ->orWhere('razon_social', 'ilike', "%{$q}%")
                      ->orWhere('numero_documento', 'ilike', "%{$q}%"));
            }))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        // KPIs (queries baratas apoyadas en los índices empresa+estado / empresa+vencimiento)
        $base = fn () => Cotizacion::deEmpresa($user->empresa_id);
        $kpis = [
            'vigentes'      => $base()->vigente()->count(),
            'monto_vigente' => round((float) $base()->vigente()->sum('total'), 2),
            'por_vencer'    => $base()->vigente()->whereBetween('fecha_vencimiento', [$hoy, $limite])->count(),
            'vencidas_sin_respuesta' => $base()->where('estado', Cotizacion::ESTADO_VENCIDA)
                ->where(fn ($c) => $c->whereNull('ultimo_contacto')
                    ->orWhereColumn('ultimo_contacto', '<', 'fecha_vencimiento'))
                ->count(),
        ];

        return Inertia::render('Ventas/Cotizaciones', [
            'cotizaciones' => $cotizaciones,
            'kpis'         => $kpis,
            'estado'       => $estado,
            'q'            => $q,
            // Incluye el cliente general ("Clientes varios") para cotizar sin
            // identificar; queda de primero para elegirlo rápido.
            'clientes'     => Cliente::deEmpresa($user->empresa_id)->activo()
                ->orderByDesc('es_cliente_general')
                ->orderBy('nombres')
                ->get(['id', 'nombres', 'apellidos', 'razon_social', 'telefono', 'es_cliente_general']),
            // Catálogo para el editor de ítems: unidades activas con su precio.
            'productos'    => Producto::deEmpresa($user->empresa_id)->activo()
                ->with(['unidades' => fn ($qq) => $qq->where('activo', true), 'unidades.unidadMedida:id,nombre'])
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'incluye_igv'])
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'nombre'      => $p->nombre,
                    'codigo'      => $p->codigo,
                    'incluye_igv' => (bool) $p->incluye_igv,
                    'unidades'    => $p->unidades->map(fn ($u) => [
                        'id'            => $u->id,
                        'nombre'        => $u->unidadMedida?->nombre ?? '',
                        'precio_venta'  => (float) $u->precio_venta,
                        'es_base'       => (bool) $u->es_base,
                    ])->values(),
                ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validarCotizacion($request);

        $cotizacion = DB::transaction(function () use ($data, $user) {
            $cotizacion = Cotizacion::create([
                'empresa_id'             => $user->empresa_id,
                'local_id'               => $user->local_id,
                'user_id'                => $user->id,
                'cliente_id'             => $data['cliente_id'],
                'numero'                 => Cotizacion::generarNumero($user->empresa_id),
                'referencia'             => $data['referencia'] ?? null,
                'estado'                 => Cotizacion::ESTADO_VIGENTE,
                'fecha'                  => $data['fecha'],
                'fecha_vencimiento'      => $data['fecha_vencimiento'] ?? null,
                'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null,
                'observacion'            => $data['observacion'] ?? null,
            ]);

            $this->guardarItems($cotizacion, $data['items'], $user->empresa_id);
            $cotizacion->recalcularTotales();

            return $cotizacion;
        });

        AuditoriaService::log('cotizacion.creada', $cotizacion, [
            'numero'     => $cotizacion->numero,
            'cliente_id' => $cotizacion->cliente_id,
            'total'      => (float) $cotizacion->total,
            'items'      => count($data['items']),
        ], $user);

        return back()->with('success', "Cotización {$cotizacion->numero} registrada correctamente.");
    }

    /**
     * Edición completa (cabecera + ítems) mientras la cotización siga viva
     * (vigente o vencida). Si al editar la nueva vigencia vuelve a estar en
     * el futuro, una vencida regresa a vigente.
     */
    public function update(Request $request, Cotizacion $cotizacion)
    {
        $user = $request->user();
        abort_if($cotizacion->empresa_id !== $user->empresa_id, 403);
        abort_unless($cotizacion->esEditable(), 422, 'Solo se pueden editar cotizaciones vigentes o vencidas.');

        $data = $this->validarCotizacion($request);

        DB::transaction(function () use ($cotizacion, $data, $user) {
            $venceFuturo = empty($data['fecha_vencimiento'])
                || $data['fecha_vencimiento'] >= now()->toDateString();

            $cotizacion->update([
                'cliente_id'             => $data['cliente_id'],
                'referencia'             => $data['referencia'] ?? null,
                'fecha'                  => $data['fecha'],
                'fecha_vencimiento'      => $data['fecha_vencimiento'] ?? null,
                'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null,
                'observacion'            => $data['observacion'] ?? null,
                'estado'                 => $venceFuturo ? Cotizacion::ESTADO_VIGENTE : Cotizacion::ESTADO_VENCIDA,
            ]);

            $cotizacion->items()->delete();
            $this->guardarItems($cotizacion, $data['items'], $user->empresa_id);
            $cotizacion->unsetRelation('items');
            $cotizacion->recalcularTotales();
        });

        AuditoriaService::log('cotizacion.editada', $cotizacion, [
            'numero' => $cotizacion->numero,
            'total'  => (float) $cotizacion->total,
            'items'  => count($data['items']),
        ], $user);

        return back()->with('success', "Cotización {$cotizacion->numero} actualizada.");
    }

    /**
     * Transiciones manuales: aceptada / rechazada / anulada (con motivo).
     * La transición a 'convertida' NO pasa por aquí: la hace el POS al crear
     * la venta (VentaController@store con cotizacion_id).
     */
    public function cambiarEstado(Request $request, Cotizacion $cotizacion)
    {
        $user = $request->user();
        abort_if($cotizacion->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'estado' => ['required', Rule::in([
                Cotizacion::ESTADO_ACEPTADA, Cotizacion::ESTADO_RECHAZADA, Cotizacion::ESTADO_ANULADA,
            ])],
            'motivo' => [Rule::requiredIf(fn () => $request->input('estado') !== Cotizacion::ESTADO_ACEPTADA), 'nullable', 'string', 'min:3', 'max:500'],
        ]);

        // Una convertida ya es venta; una anulada es cosa juzgada.
        if (in_array($cotizacion->estado, [Cotizacion::ESTADO_CONVERTIDA, Cotizacion::ESTADO_ANULADA], true)) {
            throw ValidationException::withMessages([
                'estado' => "La cotización {$cotizacion->numero} ya está {$cotizacion->estado_label} y no admite cambios de estado.",
            ]);
        }

        $anterior = $cotizacion->estado;
        $cotizacion->update([
            'estado' => $data['estado'],
            // El motivo queda en la bitácora de seguimiento, con fecha.
            'notas_seguimiento' => !empty($data['motivo'])
                ? trim(($cotizacion->notas_seguimiento ? $cotizacion->notas_seguimiento . "\n" : '')
                    . '[' . now()->toDateString() . '] ' . Cotizacion::labelDe($data['estado']) . ": {$data['motivo']}")
                : $cotizacion->notas_seguimiento,
        ]);

        AuditoriaService::log("cotizacion.{$data['estado']}", $cotizacion, [
            'numero'          => $cotizacion->numero,
            'estado_anterior' => $anterior,
            'motivo'          => $data['motivo'] ?? null,
        ], $user);

        return back()->with('success', "Cotización {$cotizacion->numero} marcada como {$cotizacion->estado_label}.");
    }

    /**
     * Registra un contacto de seguimiento con el cliente ("lo llamé, dijo que
     * la revisa el lunes"): actualiza ultimo_contacto y apila la nota.
     */
    public function registrarContacto(Request $request, Cotizacion $cotizacion)
    {
        $user = $request->user();
        abort_if($cotizacion->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'nota'  => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cotizacion->update([
            'ultimo_contacto'   => $data['fecha'],
            'notas_seguimiento' => trim(($cotizacion->notas_seguimiento ? $cotizacion->notas_seguimiento . "\n" : '')
                . "[{$data['fecha']}] {$data['nota']}"),
        ]);

        AuditoriaService::log('cotizacion.contacto', $cotizacion, [
            'numero' => $cotizacion->numero,
            'fecha'  => $data['fecha'],
            'nota'   => $data['nota'],
        ], $user);

        return back()->with('success', 'Contacto registrado.');
    }

    /** Payload JSON para imprimir la cotización en la ticketera (agente local). */
    public function ticket(Request $request, Cotizacion $cotizacion)
    {
        abort_if($cotizacion->empresa_id !== $request->user()->empresa_id, 403);

        return response()->json(app(TicketPrintService::class)->payloadDeCotizacion($cotizacion));
    }

    /** Proforma A4 imprimible / guardable como PDF (vista HTML, no Inertia). */
    public function proforma(Request $request, Cotizacion $cotizacion)
    {
        abort_if($cotizacion->empresa_id !== $request->user()->empresa_id, 403);

        $cotizacion->loadMissing(['cliente', 'items.producto', 'user', 'empresa', 'local']);
        $empresa = $cotizacion->empresa;

        // Logo incrustado como data-URI base64: así la imagen SIEMPRE carga en el
        // documento (no depende de storage:link, APP_URL ni del host). Antes se
        // pasaba una URL de Storage y salía la imagen rota.
        $logoData = null;
        if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
            $ext  = strtolower(pathinfo($empresa->logo, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
                default => 'image/jpeg',
            };
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($empresa->logo));
        }

        return view('proforma-cotizacion', [
            'cot'     => $cotizacion,
            'empresa' => $empresa,
            'local'   => $cotizacion->local,
            'cliente' => $cotizacion->cliente,
            'logoUrl' => $logoData,
        ]);
    }

    // ── Helpers privados ─────────────────────────────────────────────────

    /** Validación compartida de store/update (cabecera + ítems). */
    private function validarCotizacion(Request $request): array
    {
        $empresaId = $request->user()->empresa_id;

        return $request->validate([
            // Se permite el cliente general ("Clientes varios") en cotizaciones.
            'cliente_id'             => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)->where('activo', true)],
            'referencia'             => ['nullable', 'string', 'max:200'],
            'fecha'                  => ['required', 'date'],
            'fecha_vencimiento'      => ['nullable', 'date', 'after_or_equal:fecha'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'observacion'            => ['nullable', 'string', 'max:1000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.producto_id'    => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $empresaId)->where('activo', true)],
            'items.*.producto_unidad_id' => ['required', 'integer', 'exists:producto_unidades,id'],
            'items.*.cantidad'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario'=> ['required', 'numeric', 'min:0'],
            'items.*.descuento_item' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Crea las líneas con snapshot de nombres, validando que cada unidad
     * pertenezca a su producto (y que el descuento no supere el precio).
     */
    private function guardarItems(Cotizacion $cotizacion, array $items, int $empresaId): void
    {
        $unidades = ProductoUnidad::with('unidadMedida:id,nombre')
            ->whereIn('id', collect($items)->pluck('producto_unidad_id')->filter()->unique())
            ->get()->keyBy('id');

        $productos = Producto::deEmpresa($empresaId)
            ->whereIn('id', collect($items)->pluck('producto_id')->unique())
            ->get(['id', 'nombre'])->keyBy('id');

        foreach ($items as $idx => $item) {
            $unidad   = $unidades->get((int) $item['producto_unidad_id']);
            $producto = $productos->get((int) $item['producto_id']);

            if (!$unidad || !$producto || (int) $unidad->producto_id !== (int) $producto->id) {
                throw ValidationException::withMessages([
                    "items.{$idx}.producto_unidad_id" => 'La presentación no pertenece al producto indicado.',
                ]);
            }

            $precio    = (float) $item['precio_unitario'];
            $descuento = (float) ($item['descuento_item'] ?? 0);
            if ($descuento > $precio + 0.009) {
                throw ValidationException::withMessages([
                    "items.{$idx}.descuento_item" => 'El descuento por unidad no puede superar el precio cotizado.',
                ]);
            }

            $cotizacion->items()->create([
                'producto_id'        => $producto->id,
                'producto_unidad_id' => $unidad->id,
                'producto_nombre'    => mb_substr($producto->nombre, 0, 150),
                'unidad_nombre'      => mb_substr($unidad->unidadMedida?->nombre ?? '', 0, 50),
                'cantidad'           => round((float) $item['cantidad'], 4),
                'precio_unitario'    => round($precio, 2),
                'descuento_item'     => round($descuento, 2),
                'subtotal'           => round(($precio - $descuento) * (float) $item['cantidad'], 2),
            ]);
        }
    }
}
