<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\AuditoriaService;
use App\Support\Flota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Flota → Vehículos: los camiones de la empresa y quién los maneja. */
class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        Flota::exigir($request->user());
        $empresaId = $request->user()->empresa_id;

        // El viaje en ruta de cada vehículo: de ahí sale "En ruta".
        $enRuta = Viaje::deEmpresa($empresaId)->where('estado', 'en_ruta')->pluck('numero', 'vehiculo_id');

        $flota = app(\App\Services\FlotaService::class);

        $vehiculos = Vehiculo::deEmpresa($empresaId)
            ->with('conductor:id,nombres,apellidos')
            ->withCount(['viajes as viajes_mes' => fn ($q) => $q->where('estado', '<>', 'anulado')
                ->where('fecha', '>=', now()->startOfMonth()->toDateString())])
            ->orderByDesc('activo')->orderBy('placa')
            ->get();
        // El km real: el mayor entre la ficha y sus cargas / mantenimientos.
        $km = $flota->kmActual($vehiculos->pluck('id')->all());

        $vehiculos = $vehiculos
            ->map(fn (Vehiculo $v) => [
                ...$v->only(['id', 'placa', 'marca', 'modelo', 'anio', 'tipo', 'tarjeta_circulacion', 'conductor_id', 'estado', 'observacion', 'activo', 'mant_intervalo_km']),
                'capacidad_kg' => $v->capacidad_kg !== null ? (float) $v->capacidad_kg : null,
                // En el formulario se edita el km escrito a mano; en la tabla se ve el real.
                'km_ficha'     => $v->km_actual !== null ? (float) $v->km_actual : null,
                'km_actual'    => (float) ($km[$v->id] ?? 0) ?: null,
                'mant_base_km' => $v->mant_base_km !== null ? (float) $v->mant_base_km : null,
                'mantenimiento' => $flota->mantenimiento($v, (float) ($km[$v->id] ?? 0)),
                'conductor'    => $v->conductor?->nombre_completo,
                'en_ruta'      => $enRuta[$v->id] ?? null,
                'viajes_mes'   => $v->viajes_mes,
            ]);

        return Inertia::render('Flota/Vehiculos', [
            'vehiculos' => $vehiculos,
            'choferes'  => Conductor::deEmpresa($empresaId)->activo()->where('tipo', Conductor::CHOFER)
                ->orderBy('nombres')->get(['id', 'nombres', 'apellidos'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->nombre_completo]),
            'puede'     => [
                'crear'    => $request->user()->tienePermiso('flota.vehiculos', 'crear'),
                'editar'   => $request->user()->tienePermiso('flota.vehiculos', 'editar'),
                'eliminar' => $request->user()->tienePermiso('flota.vehiculos', 'eliminar'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        Flota::exigir($request->user());
        $vehiculo = Vehiculo::create($this->validar($request) + ['empresa_id' => $request->user()->empresa_id]);
        AuditoriaService::log('vehiculo.creado', $vehiculo, ['placa' => $vehiculo->placa], $request->user());

        return back()->with('success', "Vehículo {$vehiculo->placa} agregado.");
    }

    public function update(Request $request, Vehiculo $vehiculo)
    {
        Flota::exigir($request->user());
        abort_if($vehiculo->empresa_id !== $request->user()->empresa_id, 404);

        $vehiculo->update($this->validar($request, $vehiculo));
        AuditoriaService::log('vehiculo.editado', $vehiculo, ['placa' => $vehiculo->placa, 'cambios' => $vehiculo->getChanges()], $request->user());

        return back()->with('success', "Vehículo {$vehiculo->placa} actualizado.");
    }

    /** Con viajes no se borra (es su historia): se desactiva. */
    public function destroy(Request $request, Vehiculo $vehiculo)
    {
        Flota::exigir($request->user());
        abort_if($vehiculo->empresa_id !== $request->user()->empresa_id, 404);

        if ($vehiculo->viajes()->whereIn('estado', Viaje::ABIERTOS)->exists()) {
            return back()->with('error', "{$vehiculo->placa} tiene un viaje abierto: ciérralo o anúlalo antes.");
        }
        if ($vehiculo->viajes()->exists()) {
            $vehiculo->update(['activo' => false]);
            AuditoriaService::log('vehiculo.desactivado', $vehiculo, ['placa' => $vehiculo->placa], $request->user());

            return back()->with('success', "{$vehiculo->placa} tiene viajes: se desactivó y sigue en su historial.");
        }

        AuditoriaService::log('vehiculo.eliminado', $vehiculo, ['placa' => $vehiculo->placa], $request->user());
        $vehiculo->delete();

        return back()->with('success', 'Vehículo eliminado.');
    }

    private function validar(Request $request, ?Vehiculo $vehiculo = null): array
    {
        $empresaId = $request->user()->empresa_id;
        $request->merge(['placa' => Vehiculo::normalizarPlaca((string) $request->input('placa', ''))]);

        $data = $request->validate([
            'placa'               => ['required', 'string', 'min:5', 'max:10', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('vehiculos', 'placa')->where('empresa_id', $empresaId)->ignore($vehiculo?->id)],
            'marca'               => ['nullable', 'string', 'max:40'],
            'modelo'              => ['nullable', 'string', 'max:40'],
            'anio'                => ['nullable', 'integer', 'min:1950', 'max:' . (now()->year + 1)],
            'tipo'                => ['nullable', 'string', 'max:30'],
            'capacidad_kg'        => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tarjeta_circulacion' => ['nullable', 'string', 'max:30'],
            'conductor_id'        => ['nullable', 'integer', Rule::exists('conductores', 'id')
                ->where('empresa_id', $empresaId)->where('tipo', Conductor::CHOFER)],
            'estado'              => ['required', Rule::in(Vehiculo::ESTADOS)],
            'km_actual'           => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Preventivo cada X km; el km base es el último servicio antes de usar el sistema.
            'mant_intervalo_km'   => ['nullable', 'integer', 'min:500', 'max:200000'],
            'mant_base_km'        => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'observacion'         => ['nullable', 'string', 'max:255'],
            'activo'              => ['nullable', 'boolean'],
        ], [
            'placa.required'      => 'Escribe la placa.',
            'placa.min'           => 'La placa parece incompleta.',
            'placa.regex'         => 'La placa solo lleva letras, números y guion (p. ej. M9C-822).',
            'placa.unique'        => 'Ya tienes un vehículo con esa placa.',
            'conductor_id.exists' => 'Elige un chofer de tu empresa.',
            'anio.min'            => 'El año no es válido.',
            'anio.max'            => 'El año no es válido.',
        ]);

        $data['activo'] = $data['activo'] ?? ($vehiculo?->activo ?? true);
        $data['mant_intervalo_km'] = (int) ($data['mant_intervalo_km'] ?? null ?: ($vehiculo?->mant_intervalo_km ?? 8000));
        foreach (['marca', 'modelo', 'tipo', 'tarjeta_circulacion', 'observacion'] as $k) {
            $data[$k] = isset($data[$k]) && trim($data[$k]) !== '' ? trim($data[$k]) : null;
        }

        return $data;
    }
}
