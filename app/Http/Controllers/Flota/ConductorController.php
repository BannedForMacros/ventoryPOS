<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\Viaje;
use App\Services\AuditoriaService;
use App\Support\Flota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Flota → Conductores: choferes (con licencia) y ayudantes de reparto. */
class ConductorController extends Controller
{
    public function index(Request $request)
    {
        Flota::exigir($request->user());
        $empresaId = $request->user()->empresa_id;

        // Viajes del mes por persona, como chofer o como ayudante.
        $desde = now()->startOfMonth()->toDateString();
        $comoChofer = Viaje::deEmpresa($empresaId)->where('estado', '<>', 'anulado')->where('fecha', '>=', $desde)
            ->whereNotNull('conductor_id')->groupBy('conductor_id')->selectRaw('conductor_id, count(*) n')->pluck('n', 'conductor_id');
        $comoAyudante = DB::table('viaje_ayudantes as va')->join('viajes as v', 'v.id', '=', 'va.viaje_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', '<>', 'anulado')->where('v.fecha', '>=', $desde)
            ->groupBy('va.conductor_id')->selectRaw('va.conductor_id, count(*) n')->pluck('n', 'conductor_id');

        $conductores = Conductor::deEmpresa($empresaId)
            ->with('vehiculos:id,placa,conductor_id')
            ->orderByDesc('activo')->orderBy('tipo')->orderBy('nombres')
            ->get()
            ->map(fn (Conductor $c) => [
                ...$c->only(['id', 'tipo', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'telefono',
                    'licencia', 'licencia_categoria', 'observacion', 'activo', 'nombre_completo']),
                'licencia_vence'  => $c->licencia_vence?->toDateString(),
                'estado_licencia' => $c->estadoLicencia(),
                'vehiculos'       => $c->vehiculos->pluck('placa')->all(),
                'viajes_mes'      => (int) (($comoChofer[$c->id] ?? 0) + ($comoAyudante[$c->id] ?? 0)),
            ]);

        return Inertia::render('Flota/Conductores', [
            'conductores' => $conductores,
            'puede'       => [
                'crear'    => $request->user()->tienePermiso('flota.conductores', 'crear'),
                'editar'   => $request->user()->tienePermiso('flota.conductores', 'editar'),
                'eliminar' => $request->user()->tienePermiso('flota.conductores', 'eliminar'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        Flota::exigir($request->user());
        $conductor = Conductor::create($this->validar($request) + ['empresa_id' => $request->user()->empresa_id]);
        AuditoriaService::log('conductor.creado', $conductor, ['nombre' => $conductor->nombre_completo], $request->user());

        return back()->with('success', "{$conductor->nombre_completo} agregado.");
    }

    public function update(Request $request, Conductor $conductor)
    {
        Flota::exigir($request->user());
        abort_if($conductor->empresa_id !== $request->user()->empresa_id, 404);

        $data = $this->validar($request, $conductor);
        // Pasar a ayudante a quien maneja un camión lo dejaría asignado sin licencia.
        if ($data['tipo'] === Conductor::AYUDANTE && $conductor->vehiculos()->exists()) {
            return back()->withErrors(['tipo' => 'Está asignado como chofer de ' . $conductor->vehiculos()->pluck('placa')->join(', ') . ': quítalo del vehículo primero.']);
        }

        $conductor->update($data);
        AuditoriaService::log('conductor.editado', $conductor, ['nombre' => $conductor->nombre_completo, 'cambios' => $conductor->getChanges()], $request->user());

        return back()->with('success', "{$conductor->nombre_completo} actualizado.");
    }

    /** Con viajes no se borra: se desactiva y sigue en su historial. */
    public function destroy(Request $request, Conductor $conductor)
    {
        Flota::exigir($request->user());
        abort_if($conductor->empresa_id !== $request->user()->empresa_id, 404);

        $tieneViajes = $conductor->viajes()->exists()
            || DB::table('viaje_ayudantes')->where('conductor_id', $conductor->id)->exists();

        if ($tieneViajes) {
            $conductor->update(['activo' => false]);
            $conductor->vehiculos()->update(['conductor_id' => null]);
            AuditoriaService::log('conductor.desactivado', $conductor, ['nombre' => $conductor->nombre_completo], $request->user());

            return back()->with('success', "{$conductor->nombre_completo} tiene viajes: se desactivó y sigue en su historial.");
        }

        AuditoriaService::log('conductor.eliminado', $conductor, ['nombre' => $conductor->nombre_completo], $request->user());
        $conductor->delete();

        return back()->with('success', 'Conductor eliminado.');
    }

    private function validar(Request $request, ?Conductor $conductor = null): array
    {
        $empresaId = $request->user()->empresa_id;
        $esChofer  = $request->input('tipo', Conductor::CHOFER) === Conductor::CHOFER;

        $data = $request->validate([
            'tipo'               => ['required', Rule::in([Conductor::CHOFER, Conductor::AYUDANTE])],
            'nombres'            => ['required', 'string', 'max:80'],
            'apellidos'          => ['nullable', 'string', 'max:80'],
            'tipo_documento'     => ['required', Rule::in(['DNI', 'CE', 'PASAPORTE'])],
            'numero_documento'   => [$esChofer ? 'required' : 'nullable', 'string', 'max:15',
                Rule::unique('conductores', 'numero_documento')->where('empresa_id', $empresaId)->ignore($conductor?->id)],
            'telefono'           => ['nullable', 'string', 'max:20'],
            // La guía de remisión pide la licencia del chofer.
            'licencia'           => [$esChofer ? 'required' : 'nullable', 'string', 'max:20'],
            'licencia_categoria' => ['nullable', 'string', 'max:10'],
            'licencia_vence'     => ['nullable', 'date'],
            'observacion'        => ['nullable', 'string', 'max:255'],
            'activo'             => ['nullable', 'boolean'],
        ], [
            'nombres.required'          => 'Escribe el nombre.',
            'numero_documento.required' => 'El DNI del chofer va en la guía de remisión.',
            'numero_documento.unique'   => 'Ya tienes a alguien con ese documento.',
            'licencia.required'         => 'La licencia del chofer va en la guía de remisión.',
        ]);

        if (($data['tipo_documento'] ?? 'DNI') === 'DNI' && !empty($data['numero_documento']) && !preg_match('/^\d{8}$/', $data['numero_documento'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['numero_documento' => 'El DNI tiene 8 dígitos.']);
        }

        $data['activo'] = $data['activo'] ?? ($conductor?->activo ?? true);
        foreach (['apellidos', 'numero_documento', 'telefono', 'licencia', 'licencia_categoria', 'observacion'] as $k) {
            $data[$k] = isset($data[$k]) && trim($data[$k]) !== '' ? trim($data[$k]) : null;
        }
        if (!empty($data['licencia'])) $data['licencia'] = strtoupper($data['licencia']);
        if (!empty($data['licencia_categoria'])) $data['licencia_categoria'] = strtoupper($data['licencia_categoria']);

        return $data;
    }
}
