<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\EmpresaRequest;
use App\Models\Empresa;
use App\Services\AlmacenSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;

class EmpresaController extends Controller
{
    public function __construct(private AlmacenSyncService $almacenSync) {}

    public function index(Request $request)
    {
        // Cada usuario administra únicamente SU empresa (aislamiento multi-tenant).
        return Inertia::render('Configuracion/Empresas', [
            'empresas' => Empresa::where('id', $request->user()->empresa_id)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(EmpresaRequest $request)
    {
        // Dar de alta una empresa es una operación de onboarding (seeder/tinker),
        // no del panel de un tenant: cualquier admin de empresa podría crear
        // tenants ajenos. El alta creaba también su Cliente General (flag
        // es_cliente_general, A15) — ver git history si se rehabilita detrás
        // de un superadmin global.
        abort(403, 'La creación de empresas se gestiona fuera del panel.');
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        abort_if($empresa->id !== $request->user()->empresa_id, 403);

        $modoAnterior = $empresa->modo_almacen;
        $datos        = $request->validated();
        $modoNuevo    = $datos['modo_almacen'];

        if ($modoNuevo !== $modoAnterior && !$this->almacenSync->puedeCambiarModo($empresa)) {
            return back()->withErrors([
                'modo_almacen' => 'No se puede cambiar el modo de almacén porque la empresa ya tiene movimientos '
                    . '(entradas, transferencias o ventas).',
            ])->withInput();
        }

        // Plantilla del ticket: los campos ticket_* del form se guardan como un
        // solo JSON (ticket_config). TicketPrintService lo lee al armar el payload.
        $lineasExtra = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) ($datos['ticket_lineas_extra'] ?? ''))
        ), fn ($l) => $l !== ''));

        $datos['ticket_config'] = [
            'cliente_celular'   => (bool) ($datos['ticket_cliente_celular'] ?? true),
            'cliente_direccion' => (bool) ($datos['ticket_cliente_direccion'] ?? true),
            'mostrar_ruc'       => (bool) ($datos['ticket_mostrar_ruc'] ?? true),
            'mostrar_igv'       => (bool) ($datos['ticket_mostrar_igv'] ?? false),
            'mostrar_igv_cierre'=> (bool) ($datos['ticket_mostrar_igv_cierre'] ?? false),
            'logo_escala'       => isset($datos['ticket_logo_escala']) && $datos['ticket_logo_escala'] !== ''
                ? (int) $datos['ticket_logo_escala']
                : 100,
            'pie'               => trim((string) ($datos['ticket_pie'] ?? '')) ?: null,
            'lineas_extra'      => $lineasExtra,
        ];
        unset($datos['ticket_cliente_celular'], $datos['ticket_cliente_direccion'],
              $datos['ticket_mostrar_ruc'], $datos['ticket_mostrar_igv'], $datos['ticket_mostrar_igv_cierre'],
              $datos['ticket_logo_escala'],
              $datos['ticket_pie'], $datos['ticket_lineas_extra']);

        // "Afecta caja" por módulo: persistimos SOLO el flag `activo` de cada
        // módulo conocido; labels/defaults viven en el registro AfectaCaja, no
        // en la BD (evita guardar textos que luego queden viejos).
        if (array_key_exists('afecta_caja_config', $datos)) {
            $limpio = [];
            foreach ($datos['afecta_caja_config'] ?? [] as $modulo => $cfg) {
                if (array_key_exists($modulo, \App\Support\AfectaCaja::MODULOS)) {
                    $limpio[$modulo] = ['activo' => (bool) ($cfg['activo'] ?? false)];
                }
            }
            $datos['afecta_caja_config'] = $limpio;
        }

        // Logo: es un archivo, se maneja aparte del update de columnas. Solo se
        // reemplaza si llega un archivo nuevo; si no viene, se conserva el actual.
        unset($datos['logo']);
        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $datos['logo'] = $request->file('logo')->store('logos', 'public');
        }

        try {
            DB::transaction(function () use ($empresa, $datos, $modoAnterior) {
                $empresa->update($datos);
                $this->almacenSync->aplicarCambioModo($empresa->fresh(), $modoAnterior);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['modo_almacen' => $e->getMessage()])->withInput();
        }

        return redirect()->back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Empresa $empresa)
    {
        // Borrar una empresa cascadea usuarios y todo su historial (FK
        // cascadeOnDelete). Operación fuera del panel, igual que el alta.
        abort(403, 'La eliminación de empresas se gestiona fuera del panel.');
    }
}
