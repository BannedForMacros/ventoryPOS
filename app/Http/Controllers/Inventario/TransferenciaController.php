<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Transferencia;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TransferenciaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        // M19: paginar (25/page) y conservar filtros en navegación.
        $transferencias = Transferencia::where('empresa_id', $user->empresa_id)
            ->where(fn ($q) =>
                $q->whereIn('almacen_origen_id', $almacenIds)
                  ->orWhereIn('almacen_destino_id', $almacenIds)
            )
            ->with(['almacenOrigen.local', 'almacenDestino.local', 'user', 'userEnvio', 'userRecepcion'])
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventario/Transferencias/Index', [
            'transferencias'  => $transferencias,
            'almacenes'       => $this->scope->todosLosAlmacenes($user),
            'filters'         => $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Transferencias/Create', [
            'almacenesOrigen'  => $this->scope->almacenesOrigenTransferencia($user),
            'almacenesDestino' => $this->scope->almacenesDestinoTransferencia($user),
            'productos'        => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function show(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $transferencia->load([
            'almacenOrigen.local', 'almacenDestino.local',
            'user', 'userEnvio', 'userRecepcion',
            'detalles.producto', 'detalles.unidadMedida',
        ]);

        return Inertia::render('Inventario/Transferencias/Show', [
            'transferencia' => $transferencia,
        ]);
    }

    public function edit(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($transferencia->esAnulada(), 403, 'No se pueden editar transferencias anuladas.');

        $user = $request->user();

        $transferencia->load(['detalles.producto', 'detalles.unidadMedida']);

        return Inertia::render('Inventario/Transferencias/Edit', [
            'transferencia'    => $transferencia,
            'almacenesOrigen'  => $this->scope->almacenesOrigenTransferencia($user),
            'almacenesDestino' => $this->scope->almacenesDestinoTransferencia($user),
            'productos'        => Producto::deEmpresa($user->empresa_id)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->abortSiModoSimple($request);

        $user = $request->user();

        $data = $this->validarPayload($request);

        $origen  = Almacen::find($data['almacen_origen_id']);
        $destino = Almacen::find($data['almacen_destino_id']);
        $this->validarOrigenDestino($origen, $destino, $user->empresa_id);

        $transferencia = DB::transaction(function () use ($data, $user) {
            $transferencia = Transferencia::create([
                'empresa_id'         => $user->empresa_id,
                'almacen_origen_id'  => $data['almacen_origen_id'],
                'almacen_destino_id' => $data['almacen_destino_id'],
                'user_id'            => $user->id,
                'fecha'              => $data['fecha'],
                'observacion_envio'  => $data['observacion_envio'] ?? null,
                'estado'             => 'borrador',
            ]);

            $this->guardarDetalles($transferencia, $data['detalles']);

            // Si se pidió enviar directo
            if ($request->boolean('enviar')) {
                $transferencia->load('detalles.producto');
                $transferencia->enviar($user->id, $data['observacion_envio'] ?? null);
            }

            return $transferencia;
        });

        return redirect()->route('inventario.transferencias.index')
            ->with('success', 'Transferencia registrada correctamente.');
    }

    /**
     * Edita una transferencia en cualquier estado (excepto anulada).
     * Revierte el efecto en stock antes de modificar y lo reaplica después.
     */
    public function update(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($transferencia->esAnulada(), 403, 'No se pueden editar transferencias anuladas.');

        $user = $request->user();
        $data = $this->validarPayload($request);

        $origen  = Almacen::find($data['almacen_origen_id']);
        $destino = Almacen::find($data['almacen_destino_id']);
        $this->validarOrigenDestino($origen, $destino, $user->empresa_id);

        DB::transaction(function () use ($transferencia, $data) {
            $transferencia->load('detalles');

            // 1) Revertir efecto actual sobre stock
            $transferencia->revertirEfectoStock();

            // 2) Actualizar cabecera
            $transferencia->update([
                'almacen_origen_id'  => $data['almacen_origen_id'],
                'almacen_destino_id' => $data['almacen_destino_id'],
                'fecha'              => $data['fecha'],
                'observacion_envio'  => $data['observacion_envio'] ?? $transferencia->observacion_envio,
                'observacion_recepcion' => $data['observacion_recepcion'] ?? $transferencia->observacion_recepcion,
            ]);

            // 3) Reemplazar detalles
            $transferencia->detalles()->delete();
            $this->guardarDetalles($transferencia, $data['detalles']);

            // 4) Si en estado recibida, copiar cantidades_recibidas si vinieron en payload
            if ($transferencia->esRecibida() && isset($data['cantidades_recibidas'])) {
                foreach ($transferencia->detalles()->get() as $d) {
                    $cantRec = (float) ($data['cantidades_recibidas'][$d->id] ?? $d->cantidad_enviada);
                    $cantBaseRec = round($cantRec * (float) $d->factor_conversion, 4);
                    $d->update([
                        'cantidad_recibida'      => $cantRec,
                        'cantidad_base_recibida' => $cantBaseRec,
                        'diferencia_base'        => round($cantBaseRec - (float) $d->cantidad_base_enviada, 4),
                    ]);
                }
            }

            // 5) Reaplicar efecto en stock con los nuevos valores
            $transferencia->refresh()->load('detalles');

            // Si está enviada o recibida, validar stock origen antes de reaplicar
            if ($transferencia->esEnviada() || $transferencia->esRecibida()) {
                $transferencia->validarStockOrigen();
            }

            $transferencia->aplicarEfectoStock();
        });

        return redirect()->route('inventario.transferencias.index')
            ->with('success', 'Transferencia actualizada. El stock fue recalculado.');
    }

    public function enviar(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $obs = $request->input('observacion_envio');
        $transferencia->load('detalles.producto');

        try {
            $transferencia->enviar($request->user()->id, $obs);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Transferencia enviada. Stock descontado del almacén origen.');
    }

    public function recibir(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validate([
            'cantidades'              => 'required|array',
            'cantidades.*'            => 'required|numeric|min:0',
            'observacion_recepcion'   => 'nullable|string|max:500',
        ]);

        $transferencia->load('detalles');
        $transferencia->recibir(
            $data['cantidades'],
            $request->user()->id,
            $data['observacion_recepcion'] ?? null,
        );

        return redirect()->back()->with('success', 'Recepción confirmada. Stock actualizado en el almacén destino.');
    }

    public function anular(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);

        $transferencia->load('detalles');
        $transferencia->anular();

        return redirect()->back()->with('success', 'Transferencia anulada. Movimientos de stock revertidos.');
    }

    public function destroy(Request $request, Transferencia $transferencia)
    {
        $this->abortSiModoSimple($request);
        abort_if($transferencia->empresa_id !== $request->user()->empresa_id, 403);
        abort_if(!$transferencia->esBorrador(), 403, 'Solo se pueden eliminar transferencias en borrador. Si está enviada o recibida, anúlala en lugar de eliminar.');

        // M18: snapshot antes de borrar para trazabilidad post-mortem.
        $transferencia->loadMissing('detalles.producto');
        $snapshot = [
            'almacen_origen_id'  => $transferencia->almacen_origen_id,
            'almacen_destino_id' => $transferencia->almacen_destino_id,
            'fecha'              => $transferencia->fecha?->toDateString(),
            'total_items'        => $transferencia->detalles->count(),
            'detalles'           => $transferencia->detalles->map(fn ($d) => [
                'producto_id'           => $d->producto_id,
                'producto_nombre'       => $d->producto?->nombre,
                'cantidad_base_enviada' => (float) $d->cantidad_base_enviada,
            ])->all(),
        ];

        $transferencia->detalles()->delete();
        $transferencia->delete();

        \App\Services\AuditoriaService::log('transferencia.eliminada', $transferencia, $snapshot, $request->user());

        return redirect()->back()->with('success', 'Transferencia eliminada.');
    }

    // ── Helpers privados ──────────────────────────────────────

    private function validarPayload(Request $request): array
    {
        return $request->validate([
            'almacen_origen_id'  => 'required|exists:almacenes,id',
            'almacen_destino_id' => 'required|exists:almacenes,id|different:almacen_origen_id',
            'fecha'              => 'required|date',
            'observacion_envio'      => 'nullable|string|max:500',
            'observacion_recepcion'  => 'nullable|string|max:500',
            'detalles'           => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.observacion'       => 'nullable|string',
            'cantidades_recibidas'   => 'nullable|array',
            'cantidades_recibidas.*' => 'nullable|numeric|min:0',
        ]);
    }

    private function validarOrigenDestino(Almacen $origen, Almacen $destino, int $empresaId): void
    {
        abort_if($origen->empresa_id !== $empresaId, 403);
        abort_if($destino->empresa_id !== $empresaId, 403);

        if (!$origen->esCentral()) {
            abort(422, 'El origen de una transferencia debe ser el almacén central.');
        }

        if (!$destino->esLocal()) {
            abort(422, 'El destino de una transferencia debe ser un almacén de local.');
        }
    }

    private function guardarDetalles(Transferencia $transferencia, array $detalles): void
    {
        foreach ($detalles as $d) {
            $cantidadEnviada     = (float) $d['cantidad'];
            $factor              = (float) $d['factor_conversion'];
            $cantidadBaseEnviada = round($cantidadEnviada * $factor, 4);

            // Capturar costo promedio actual del origen (snapshot)
            $stockOrigen = Stock::where('almacen_id', $transferencia->almacen_origen_id)
                ->where('producto_id', $d['producto_id'])
                ->first();
            $costoUnitario = $stockOrigen ? (float) $stockOrigen->costo_promedio : 0;

            $transferencia->detalles()->create([
                'producto_id'           => $d['producto_id'],
                'unidad_medida_id'      => $d['unidad_medida_id'],
                'cantidad_enviada'      => $cantidadEnviada,
                'factor_conversion'     => $factor,
                'cantidad_base_enviada' => $cantidadBaseEnviada,
                'costo_unitario'        => $costoUnitario,
                'observacion'           => $d['observacion'] ?? null,
            ]);
        }
    }

    private function abortSiModoSimple(Request $request): void
    {
        $empresa = $request->user()->empresa;
        abort_if($empresa->usaModoSimple(), 403, 'Las transferencias no están disponibles en modo simple.');
    }
}
