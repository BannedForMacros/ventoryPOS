<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gastos\GastoController;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\VehiculoMantenimiento;
use App\Models\Viaje;
use App\Services\AuditoriaService;
use App\Services\FlotaService;
use App\Support\Flota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Flota, Fase B: resumen (alertas y utilidad por placa), ficha de cada
 * vehículo (combustible, mantenimiento, documentos, viajes) y documentos.
 *
 * El combustible y el mantenimiento se registran con el MISMO modal de gastos
 * (GastoController): esta pantalla no mueve dinero por su cuenta.
 */
class FlotaController extends Controller
{
    /** Archivos de documentos: privados, se sirven solo a usuarios de la empresa. */
    private const DISCO = 'local';

    public function __construct(private FlotaService $flota) {}

    public function resumen(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        [$desde, $hasta, $periodo] = $this->periodo($request);

        $conceptos = GastoConcepto::where('empresa_id', $user->empresa_id)->where('activo', true)
            ->with('tipo:id,nombre')->orderBy('nombre')->get(['id', 'gasto_tipo_id', 'nombre', 'flota_uso'])
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'tipo' => $c->tipo?->nombre, 'flota_uso' => $c->flota_uso]);

        return Inertia::render('Flota/Resumen', [
            'alertas'   => $this->flota->alertas($user->empresa_id),
            'utilidad'  => $this->flota->utilidad($user->empresa_id, $desde, $hasta),
            'periodo'   => ['clave' => $periodo, 'desde' => $desde, 'hasta' => $hasta],
            'conceptos' => $conceptos,
            'puede'     => ['editar' => $user->tienePermiso('flota.resumen', 'editar')],
        ]);
    }

    /** Qué conceptos de gasto son combustible y cuáles mantenimiento. */
    public function conceptos(Request $request)
    {
        $user = $request->user();
        Flota::exigir($user);

        $data = $request->validate([
            'combustible'     => ['array'],
            'combustible.*'   => ['integer'],
            'mantenimiento'   => ['array'],
            'mantenimiento.*' => ['integer'],
        ]);
        $comb = array_map('intval', $data['combustible'] ?? []);
        $mant = array_values(array_diff(array_map('intval', $data['mantenimiento'] ?? []), $comb));

        DB::transaction(function () use ($user, $comb, $mant) {
            $base = fn () => GastoConcepto::where('empresa_id', $user->empresa_id);
            $base()->whereNotNull('flota_uso')->update(['flota_uso' => null]);
            if ($comb) $base()->whereIn('id', $comb)->update(['flota_uso' => 'combustible']);
            if ($mant) $base()->whereIn('id', $mant)->update(['flota_uso' => 'mantenimiento']);
        });
        AuditoriaService::log('flota.conceptos_actualizados', $user->empresa, ['combustible' => $comb, 'mantenimiento' => $mant], $user);

        return back()->with('success', 'Listo: esos conceptos ahora piden su ficha de combustible o mantenimiento.');
    }

    /** Ficha del vehículo: su historial completo por placa. */
    public function vehiculo(Request $request, Vehiculo $vehiculo)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($vehiculo->empresa_id !== $user->empresa_id, 404);

        $vehiculo->load('conductor:id,nombres,apellidos');
        $km = (float) ($this->flota->kmActual([$vehiculo->id])[$vehiculo->id] ?? 0);
        [$desde, $hasta, $periodo] = $this->periodo($request, 'mes');

        $mantenimientos = VehiculoMantenimiento::where('vehiculo_id', $vehiculo->id)
            ->whereHas('gasto')->with('gasto:id,fecha,monto,comentario')
            ->get()
            ->sortByDesc(fn ($m) => $m->gasto->fecha->toDateString() . sprintf('%012.1f', (float) $m->km))
            ->values()
            ->map(fn ($m) => [
                'id' => $m->id, 'gasto_id' => $m->gasto_id, 'tipo' => $m->tipo,
                'fecha' => $m->gasto->fecha->toDateString(), 'monto' => (float) $m->gasto->monto,
                'km' => $m->km !== null ? (float) $m->km : null, 'taller' => $m->taller,
                'detalle' => $m->detalle ?: $m->gasto->comentario, 'dias_parado' => $m->dias_parado,
            ]);

        $hoy = Carbon::today();
        $documentos = VehiculoDocumento::where('vehiculo_id', $vehiculo->id)->orderBy('tipo')->orderByDesc('vence')->get()
            ->map(fn ($d) => [
                'id' => $d->id, 'tipo' => $d->tipo, 'numero' => $d->numero,
                'emision' => $d->emision?->toDateString(), 'vence' => $d->vence?->toDateString(),
                'estado' => $d->estado($hoy), 'archivo' => (bool) $d->archivo, 'archivo_nombre' => $d->archivo_nombre,
                'observacion' => $d->observacion,
            ]);

        $viajes = Viaje::where('vehiculo_id', $vehiculo->id)->with('conductor:id,nombres,apellidos')->withCount('paradas')
            ->orderByDesc('fecha')->orderByDesc('id')->limit(15)->get()
            ->map(fn ($v) => [
                'id' => $v->id, 'numero' => $v->numero, 'tipo' => $v->tipo, 'estado' => $v->estado,
                'fecha' => $v->fecha?->toDateString(), 'chofer' => $v->conductor?->nombre_completo, 'paradas' => $v->paradas_count,
            ]);

        // Otros gastos cargados a la placa (peajes, viáticos, letras…).
        $otros = Gasto::where('vehiculo_id', $vehiculo->id)
            ->whereDoesntHave('combustible')->whereDoesntHave('mantenimiento')
            ->with('concepto:id,nombre')->orderByDesc('fecha')->orderByDesc('id')->limit(20)->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'fecha' => $g->fecha->toDateString(), 'monto' => (float) $g->monto,
                'concepto' => $g->concepto?->nombre, 'comentario' => $g->comentario,
            ]);

        $util = collect($this->flota->utilidad($user->empresa_id, $desde, $hasta))->firstWhere('vehiculo_id', $vehiculo->id);

        return Inertia::render('Flota/Vehiculo', [
            'vehiculo'       => [
                ...$vehiculo->only(['id', 'placa', 'marca', 'modelo', 'anio', 'tipo', 'estado', 'activo', 'tarjeta_circulacion', 'mant_intervalo_km']),
                'capacidad_kg' => $vehiculo->capacidad_kg !== null ? (float) $vehiculo->capacidad_kg : null,
                'conductor_id' => $vehiculo->conductor_id,
                'conductor'    => $vehiculo->conductor?->nombre_completo,
                'km_actual'    => $km,
                'en_ruta'      => Viaje::where('vehiculo_id', $vehiculo->id)->where('estado', 'en_ruta')->value('numero'),
            ],
            'mantenimiento'  => $this->flota->mantenimiento($vehiculo, $km),
            'combustible'    => $this->flota->rendimiento($vehiculo->id),
            'mantenimientos' => $mantenimientos,
            'documentos'     => $documentos,
            'tiposDocumento' => VehiculoDocumento::TIPOS,
            'capacidades'    => $vehiculo->capacidades()->with('producto:id,nombre,peso_kg')->get()
                ->map(fn ($c) => ['producto_id' => $c->producto_id, 'producto' => $c->producto?->nombre, 'max_unidades' => (float) $c->max_unidades,
                    'peso_kg' => $c->producto?->peso_kg !== null ? (float) $c->producto->peso_kg : null]),
            'productos'      => \App\Models\Producto::where('empresa_id', $user->empresa_id)->where('activo', true)->where('tipo', 'producto')
                ->orderBy('nombre')->get(['id', 'nombre', 'peso_kg'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->nombre, 'peso_kg' => $p->peso_kg !== null ? (float) $p->peso_kg : null]),
            'viajes'         => $viajes,
            'otrosGastos'    => $otros,
            'utilidad'       => $util,
            'periodo'        => ['clave' => $periodo, 'desde' => $desde, 'hasta' => $hasta],
            // Para abrir el MISMO modal de gastos con la placa puesta.
            'gasto'          => app(GastoController::class)->datosFormulario($user),
            'conceptosFlota' => GastoConcepto::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->whereNotNull('flota_uso')->get(['id', 'gasto_tipo_id', 'flota_uso']),
            'puede'          => [
                'editar'  => $user->tienePermiso('flota.vehiculos', 'editar'),
                'gastos'  => $user->tienePermiso('gastos', 'crear'),
            ],
        ]);
    }

    /** Unidades máximas por producto en este camión (límite por volumen). */
    public function capacidades(Request $request, Vehiculo $vehiculo)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($vehiculo->empresa_id !== $user->empresa_id, 404);

        $data = $request->validate([
            'capacidades'                => ['array'],
            'capacidades.*.producto_id'  => ['required', 'integer', 'distinct', \App\Support\EnEmpresa::existe('productos')],
            'capacidades.*.max_unidades' => ['required', 'numeric', 'min:1', 'max:99999999'],
        ], ['capacidades.*.producto_id.distinct' => 'Ese producto ya está en la lista.']);

        DB::transaction(function () use ($vehiculo, $data) {
            $vehiculo->capacidades()->delete();
            foreach ($data['capacidades'] ?? [] as $c) {
                $vehiculo->capacidades()->create(['producto_id' => $c['producto_id'], 'max_unidades' => round((float) $c['max_unidades'], 2)]);
            }
        });
        AuditoriaService::log('vehiculo.capacidades', $vehiculo, ['productos' => count($data['capacidades'] ?? [])], $user);

        return back()->with('success', "Capacidad por producto de {$vehiculo->placa} guardada.");
    }

    // ── Documentos ───────────────────────────────────────────────────────────

    public function guardarDocumento(Request $request, Vehiculo $vehiculo, ?VehiculoDocumento $documento = null)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($vehiculo->empresa_id !== $user->empresa_id, 404);
        abort_if($documento && $documento->vehiculo_id !== $vehiculo->id, 404);

        $data = $request->validate([
            'tipo'        => ['required', 'string', 'max:40'],
            'numero'      => ['nullable', 'string', 'max:60'],
            'emision'     => ['nullable', 'date'],
            'vence'       => ['nullable', 'date', 'after_or_equal:emision'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'archivo'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'quitar_archivo' => ['nullable', 'boolean'],
        ], [
            'tipo.required'        => 'Indica qué documento es.',
            'vence.after_or_equal' => 'El vencimiento no puede ser antes de la emisión.',
            'archivo.mimes'        => 'Sube un PDF o una foto (JPG, PNG).',
            'archivo.max'          => 'El archivo no puede pasar de 8 MB.',
        ]);

        $valores = [
            'tipo'        => trim($data['tipo']),
            'numero'      => trim((string) ($data['numero'] ?? '')) ?: null,
            'emision'     => $data['emision'] ?? null,
            'vence'       => $data['vence'] ?? null,
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
        ];

        $anterior = $documento?->archivo;
        if ($request->hasFile('archivo')) {
            $f = $request->file('archivo');
            $valores['archivo'] = $f->store("flota/{$user->empresa_id}/{$vehiculo->id}", self::DISCO);
            $valores['archivo_nombre'] = mb_substr($f->getClientOriginalName(), 0, 150);
        } elseif ($request->boolean('quitar_archivo')) {
            $valores['archivo'] = null;
            $valores['archivo_nombre'] = null;
        }

        $documento
            ? $documento->update($valores)
            : $documento = VehiculoDocumento::create($valores + ['empresa_id' => $user->empresa_id, 'vehiculo_id' => $vehiculo->id]);

        if ($anterior && $anterior !== $documento->archivo) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
        AuditoriaService::log('vehiculo.documento_guardado', $vehiculo, ['tipo' => $documento->tipo, 'vence' => $documento->vence?->toDateString()], $user);

        return back()->with('success', "{$documento->tipo} guardado.");
    }

    public function eliminarDocumento(Request $request, VehiculoDocumento $documento)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($documento->empresa_id !== $user->empresa_id, 404);

        if ($documento->archivo) Storage::disk(self::DISCO)->delete($documento->archivo);
        AuditoriaService::log('vehiculo.documento_eliminado', $documento->vehiculo, ['tipo' => $documento->tipo], $user);
        $documento->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    public function archivoDocumento(Request $request, VehiculoDocumento $documento)
    {
        $user = $request->user();
        Flota::exigir($user);
        abort_if($documento->empresa_id !== $user->empresa_id, 404);
        abort_unless($documento->archivo && Storage::disk(self::DISCO)->exists($documento->archivo), 404);

        return Storage::disk(self::DISCO)->response($documento->archivo, $documento->archivo_nombre ?? basename($documento->archivo));
    }

    // ── Interioridades ───────────────────────────────────────────────────────

    /**
     * Período del resumen. La semana va de lunes a hoy: con la utilidad semanal
     * se paga a choferes y ayudantes.
     *
     * @return array{0:string, 1:string, 2:string}
     */
    private function periodo(Request $request, string $porDefecto = 'semana'): array
    {
        $clave = in_array($request->input('periodo'), ['semana', 'semana_pasada', 'mes', 'mes_pasado', 'rango'], true)
            ? $request->input('periodo') : $porDefecto;
        $hoy = Carbon::today();

        [$desde, $hasta] = match ($clave) {
            'semana_pasada' => [$hoy->copy()->startOfWeek()->subWeek(), $hoy->copy()->startOfWeek()->subDay()],
            'mes'           => [$hoy->copy()->startOfMonth(), $hoy],
            'mes_pasado'    => [$hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()],
            'rango'         => [
                Carbon::parse($request->input('desde', $hoy->toDateString())),
                Carbon::parse($request->input('hasta', $hoy->toDateString())),
            ],
            default         => [$hoy->copy()->startOfWeek(), $hoy],
        };
        if ($desde->gt($hasta)) [$desde, $hasta] = [$hasta, $desde];

        return [$desde->toDateString(), $hasta->toDateString(), $clave];
    }
}
