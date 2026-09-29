<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\RutaEntrega;
use App\Services\AuditoriaService;
use App\Support\ConfigEntregas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Configuración → Entregas: activar la función, el monto del aviso, qué se
 * exige en un envío y las rutas de reparto de la empresa.
 */
class EntregaConfigController extends Controller
{
    public function index(Request $request)
    {
        $empresa = $request->user()->empresa;

        return Inertia::render('Configuracion/Entregas', [
            'config' => ConfigEntregas::de($empresa),
            'catalogoTextos' => ConfigEntregas::catalogoTextos(),
            'rutas'  => RutaEntrega::deEmpresa($empresa->id)
                ->withCount('ventas')
                ->orderBy('orden')->orderBy('id')
                ->get(['id', 'nombre', 'zona', 'orden', 'activo']),
            // El envío que sale al entregarse se apoya en la bandeja de despachos.
            'usaDespachoAlmacen' => (bool) $empresa->usa_despacho_almacen,
            'puedeEditar'        => $request->user()->tienePermiso('config.entregas', 'editar'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'activo'                 => ['required', 'boolean'],
            'aviso_monto'            => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'ruta_obligatoria'       => ['required', 'boolean'],
            'fecha_obligatoria'      => ['required', 'boolean'],
            'envio_sale_al_entregar' => ['required', 'boolean'],
            'textos'                 => ['nullable', 'array'],
            'textos.*'               => ['nullable', 'string', 'max:40'],
        ]);

        $empresa = $request->user()->empresa;
        $empresa->update([
            'usa_entregas'   => $data['activo'],
            'entrega_config' => [
                'aviso_monto'            => isset($data['aviso_monto']) && (float) $data['aviso_monto'] > 0 ? round((float) $data['aviso_monto'], 2) : null,
                'ruta_obligatoria'       => $data['ruta_obligatoria'],
                'fecha_obligatoria'      => $data['fecha_obligatoria'],
                'envio_sale_al_entregar' => $data['envio_sale_al_entregar'],
                // Se guardan completos; vacío = el texto por defecto.
                'textos'                 => ConfigEntregas::textos((array) ($data['textos'] ?? [])),
            ],
        ]);

        AuditoriaService::log('entregas.configuracion_actualizada', $empresa, ['activo' => $data['activo']], $request->user());

        return back()->with('success', 'Configuración de entregas guardada.');
    }

    public function guardarRuta(Request $request, ?RutaEntrega $ruta = null)
    {
        $empresaId = $request->user()->empresa_id;
        abort_if($ruta && $ruta->empresa_id !== $empresaId, 403);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:60',
                Rule::unique('rutas_entrega', 'nombre')->where('empresa_id', $empresaId)->ignore($ruta?->id)],
            'zona'   => ['nullable', 'string', 'max:120'],
            'orden'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.unique' => 'Ya tienes una ruta con ese nombre.',
        ]);

        $valores = [
            'nombre' => trim($data['nombre']),
            'zona'   => trim((string) ($data['zona'] ?? '')) ?: null,
            'activo' => $data['activo'] ?? ($ruta?->activo ?? true),
            'orden'  => $data['orden'] ?? ($ruta?->orden ?? ((int) RutaEntrega::deEmpresa($empresaId)->max('orden') + 1)),
        ];

        $ruta ? $ruta->update($valores) : RutaEntrega::create($valores + ['empresa_id' => $empresaId]);

        return back()->with('success', $ruta ? 'Ruta actualizada.' : 'Ruta agregada.');
    }

    public function eliminarRuta(Request $request, RutaEntrega $ruta)
    {
        abort_if($ruta->empresa_id !== $request->user()->empresa_id, 403);

        // Una ruta con ventas no se borra: se desactiva y sigue en su historial.
        if ($ruta->ventas()->exists()) {
            $ruta->update(['activo' => false]);

            return back()->with('success', "«{$ruta->nombre}» ya tiene ventas: se desactivó y no aparecerá en ventas nuevas.");
        }

        $ruta->delete();

        return back()->with('success', 'Ruta eliminada.');
    }
}
