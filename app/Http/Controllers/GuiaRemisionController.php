<?php

namespace App\Http\Controllers;

use App\Jobs\EmitirGuiaRemision;
use App\Models\Guia;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Guias\GuiaAContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Guías de remisión desde ventoryPOS.
 *
 * ─── LAS REGLAS NO VIVEN AQUÍ ──────────────────────────────────────────────────
 *
 * Qué campos pide cada motivo llega de FacturaMac en `/guias/catalogos`, y
 * FacturaMac lo saca del contrato compartido. Esta pantalla solo enseña u oculta.
 * Es lo que hace que el formulario de aquí y el del portal coincidan sin que nadie
 * los coordine, y que el día que SUNAT cambie una regla se toque un solo sitio.
 *
 * ─── LA GUÍA SE REGISTRA SIEMPRE ───────────────────────────────────────────────
 *
 * Igual que una venta: se guarda aquí y el envío ocurre detrás. Quien despacha no
 * puede quedarse esperando a que SUNAT conteste con el camión cargado.
 *
 * ─── Y NO TOCA STOCK ───────────────────────────────────────────────────────────
 *
 * Ni una línea de este controlador mueve inventario. El stock lo mueven la venta, el
 * despacho y la transferencia, exactamente como antes. Una guía es el documento de
 * ese movimiento, no el movimiento.
 */
class GuiaRemisionController extends Controller
{
    public function __construct(
        private readonly GuiaAContrato $mapper,
        private readonly FacturacionEmpresa $facturacion,
    ) {
    }

    public function index(Request $request): Response
    {
        $empresaId = (int) $request->user()->empresa_id;

        $guias = Guia::deEmpresa($empresaId)
            ->with(['cliente:id,razon_social,nombres,apellidos', 'venta:id,numero'])
            ->when($request->input('buscar'), function ($q, $texto) {
                $texto = trim($texto);
                $q->where(fn ($s) => $s
                    ->where('numero', 'ilike', "%{$texto}%")
                    ->orWhere('destinatario', 'ilike', "%{$texto}%")
                    ->orWhere('referencia_externa', 'ilike', "%{$texto}%"));
            })
            ->when($request->input('estado'), fn ($q, $e) => $q->where('estado', $e))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Guias/Index', [
            'guias'   => $guias->through(fn (Guia $g) => $this->paraLista($g)),
            'filtros' => $request->only('buscar', 'estado'),
            'emision' => $this->estadoDeLaEmision($empresaId),
        ]);
    }

    /**
     * El formulario, ya con los catálogos de FacturaMac.
     *
     * Si FacturaMac no responde, la pantalla se abre igual y lo dice. Preferible a
     * un error en blanco: así se ve que el problema es la conexión y no el dato.
     */
    public function create(Request $request): Response
    {
        $empresaId = (int) $request->user()->empresa_id;

        return Inertia::render('Guias/Create', [
            'catalogos' => $this->catalogos($empresaId),
            'emision'   => $this->estadoDeLaEmision($empresaId),
            'venta'     => $request->filled('venta') ? $this->ventaParaGuia($request, $empresaId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = (int) $request->user()->empresa_id;

        // Solo la FORMA. Lo que tiene consecuencias fiscales lo valida el contrato,
        // en FacturaMac, y su error vuelve con el campo exacto.
        $datos = $request->validate([
            'motivo'              => 'required|string',
            'modalidad'           => 'required|string',
            'fecha_inicio'        => 'required|date_format:Y-m-d',
            'peso_total'          => 'required|numeric|min:0.001',
            'partida.direccion'   => 'required|string|max:255',
            'partida.ubigeo'      => 'required|digits:6',
            'llegada.direccion'   => 'required|string|max:255',
            'llegada.ubigeo'      => 'required|digits:6',
            'items'               => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.cantidad'    => 'required|numeric|min:0.001',
        ]);

        $form = $request->all();

        // La clave la pone el servidor, nunca el navegador: es lo que impide que un
        // doble clic consuma dos números de SUNAT para el mismo despacho.
        $form['idempotency_key']    ??= 'pos:' . Str::uuid()->toString();
        $form['referencia_externa'] ??= 'POS-' . now()->format('YmdHis');

        try {
            $payload = $this->mapper->mapear($form, $empresaId);
        } catch (Throwable $e) {
            return back()->withErrors(['motivo' => 'No se pudo preparar la guía: ' . $e->getMessage()])->withInput();
        }

        $guia = DB::transaction(fn () => Guia::create([
            'empresa_id'         => $empresaId,
            'venta_id'           => $request->input('venta_id'),
            'transferencia_id'   => $request->input('transferencia_id'),
            'cliente_id'         => $request->input('cliente_id'),
            'estado'             => 'pendiente',
            'puede_trasladar'    => false,
            'payload'            => $payload,
            'motivo'             => $datos['motivo'],
            'modalidad'          => $datos['modalidad'],
            'fecha_traslado'     => $datos['fecha_inicio'],
            'destinatario'       => $payload['destinatario']['nombre'] ?? null,
            'llegada_direccion'  => $datos['llegada']['direccion'],
            'idempotency_key'    => $form['idempotency_key'],
            'referencia_externa' => $form['referencia_externa'],
            'user_id'            => $request->user()->id,
        ]));

        EmitirGuiaRemision::dispatch($guia);

        return redirect()
            ->route('guias.show', $guia)
            ->with('success', 'Guía registrada. Se está enviando a SUNAT.');
    }

    public function show(Request $request, Guia $guia): Response
    {
        abort_unless((int) $guia->empresa_id === (int) $request->user()->empresa_id, 404);

        return Inertia::render('Guias/Show', [
            'guia' => [
                ...$guia->toArray(),
                'estado_label'     => $guia->estado_label,
                'estado_color'     => $guia->estado_color,
                'espera_respuesta' => $guia->esperaRespuesta(),
                'reintentable'     => $guia->reintentable(),
                'terminal'         => $guia->terminal(),
            ],
        ]);
    }

    /**
     * Vuelve a intentarlo.
     *
     * Solo lo que nunca llegó a SUNAT. Un rechazo es determinista: reenviar lo mismo
     * da el mismo rechazo, y lo que toca es corregir y emitir otra.
     */
    public function reintentar(Request $request, Guia $guia)
    {
        abort_unless((int) $guia->empresa_id === (int) $request->user()->empresa_id, 404);

        if (! $guia->reintentable() && $guia->estado !== Guia::ESTADO_ERROR_MAPEO) {
            return back()->with('error', 'Esa guía no se puede reenviar: ' . ($guia->aviso ?? $guia->estado_label));
        }

        EmitirGuiaRemision::dispatch($guia);

        return back()->with('success', 'Guía puesta de nuevo en cola.');
    }

    // ── Interioridades ──────────────────────────────────────────────────────

    /**
     * Los catálogos de FacturaMac, o por qué no se pudieron leer.
     *
     * @return array<string, mixed>
     */
    private function catalogos(int $empresaId): array
    {
        if (! $this->facturacion->activa($empresaId)) {
            return ['disponible' => false, 'motivo' => 'La emisión electrónica está apagada para esta empresa.'];
        }

        try {
            return ['disponible' => true] + $this->facturacion->cliente($empresaId)->catalogosGuias();
        } catch (Throwable $e) {
            return ['disponible' => false, 'motivo' => 'No se pudo contactar con FacturaMac: ' . $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function estadoDeLaEmision(int $empresaId): array
    {
        $catalogos = $this->catalogos($empresaId);

        return [
            'disponible' => ($catalogos['disponible'] ?? false) && ($catalogos['activas'] ?? false),
            'motivo'     => $catalogos['motivo'] ?? $catalogos['impedimento'] ?? null,
            'ensayo'     => $catalogos['ensayo'] ?? null,
        ];
    }

    /**
     * Una venta traída al formulario: sus líneas y su cliente, listos para despachar.
     *
     * @return array<string, mixed>|null
     */
    private function ventaParaGuia(Request $request, int $empresaId): ?array
    {
        $venta = \App\Models\Venta::where('empresa_id', $empresaId)
            ->with(['items', 'cliente', 'comprobanteElectronico'])
            ->find($request->input('venta'));

        if ($venta === null) {
            return null;
        }

        return [
            'id'         => $venta->id,
            'numero'     => $venta->numero,
            'cliente_id' => $venta->cliente_id,
            'cliente'    => $venta->cliente?->razon_social ?: $venta->cliente?->nombre_completo,
            'comprobante' => $venta->comprobanteElectronico?->numero,
            'items' => $venta->items->map(fn ($i) => [
                'descripcion' => $i->producto_nombre,
                'cantidad'    => (float) $i->cantidad,
                'unidad'      => 'NIU',
                'codigo'      => null,
                'peso'        => null,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function paraLista(Guia $guia): array
    {
        return [
            'id'              => $guia->id,
            'numero'          => $guia->numero ?? '—',
            'fecha_traslado'  => $guia->fecha_traslado?->format('Y-m-d'),
            'destinatario'    => $guia->destinatario ?? '—',
            'llegada'         => $guia->llegada_direccion,
            'estado'          => $guia->estado,
            'estado_label'    => $guia->estado_label,
            'estado_color'    => $guia->estado_color,
            // Lo único que de verdad se quiere saber mirando la lista.
            'puede_trasladar' => $guia->puede_trasladar,
            'reintentable'    => $guia->reintentable() || $guia->estado === Guia::ESTADO_ERROR_MAPEO,
            'venta'           => $guia->venta?->numero,
        ];
    }
}
