<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\RutaEntrega;
use App\Services\EntregaPendienteService;
use App\Services\TicketPrintService;
use App\Support\ConfigEntregas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Bandeja del almacenero: mercadería vendida que quedó pendiente de despacho
 * (despacho en almacén). El stock no salió al vender; sale cuando el almacenero
 * confirma la entrega aquí.
 *
 * Con Entregas activado es también la hoja de reparto: se filtra por día y por
 * ruta, y sale ordenada por la hora programada.
 */
class DespachoController extends Controller
{
    private const CUANDO = ['todos', 'hoy', 'manana', 'atrasados', 'sin_fecha'];

    public function __construct(
        private EntregaPendienteService $entregas,
    ) {}

    public function index(Request $request)
    {
        $user    = $request->user();
        $buscar  = trim((string) $request->input('buscar', ''));
        $cuando  = in_array($request->input('cuando'), self::CUANDO, true) ? $request->input('cuando') : 'todos';
        $fecha   = $request->date('fecha')?->toDateString();
        $rutaId  = $request->integer('ruta_id') ?: null;
        $usaEntregas = ConfigEntregas::de($user->empresa)['activo'];

        $base = fn () => ClienteAnticipo::query()
            ->where('cliente_anticipos.empresa_id', $user->empresa_id)
            ->where('cliente_anticipos.tipo_valorizacion', 'material')
            ->where('cliente_anticipos.estado', 'activo')
            ->whereNotNull('cliente_anticipos.venta_id')
            ->join('ventas', 'ventas.id', '=', 'cliente_anticipos.venta_id')
            ->when($user->local_id, fn ($q) => $q->where('ventas.local_id', $user->local_id));

        $pendientes = $this->filtrarCuando($base(), $fecha ? 'fecha' : $cuando, $fecha)
            ->when($rutaId, fn ($q) => $q->where('ventas.ruta_entrega_id', $rutaId))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('cliente_anticipos.observacion', 'ilike', "%{$buscar}%")
                ->orWhere('ventas.numero', 'ilike', "%{$buscar}%")
                ->orWhere('ventas.cliente_direccion', 'ilike', "%{$buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c
                    ->where('nombres', 'ilike', "%{$buscar}%")
                    ->orWhere('apellidos', 'ilike', "%{$buscar}%")
                    ->orWhere('razon_social', 'ilike', "%{$buscar}%"))
                ->orWhereHas('items.producto', fn ($p) => $p->where('nombre', 'ilike', "%{$buscar}%"))))
            ->with([
                'cliente:id,nombres,apellidos,razon_social,telefono,direccion,es_cliente_general',
                'user:id,name',
                'venta:id,numero,local_id,fecha_venta,tipo_entrega,ruta_entrega_id,entrega_programada,cliente_telefono,cliente_direccion,observacion,saldo_pendiente,total',
                'venta.local:id,nombre',
                'venta.rutaEntrega:id,nombre,zona',
                'items.producto:id,nombre,precio_venta',
                'items.unidad:id,precio_venta',
            ])
            // Primero lo que tiene hora, de más temprano a más tarde.
            ->orderByRaw('ventas.entrega_programada ASC NULLS LAST')
            ->orderByDesc('cliente_anticipos.created_at')
            ->select('cliente_anticipos.*')
            ->paginate(25)->withQueryString();

        return Inertia::render('Despachos/Index', [
            'pendientes'  => $pendientes,
            'buscar'      => $buscar,
            'filtros'     => ['cuando' => $fecha ? 'fecha' : $cuando, 'fecha' => $fecha, 'ruta_id' => $rutaId],
            'usaEntregas' => $usaEntregas,
            'conteos'     => $usaEntregas
                ? collect(self::CUANDO)->mapWithKeys(fn ($c) => [$c => $this->filtrarCuando($base(), $c)->count()])
                : null,
            'rutas'       => $usaEntregas
                ? RutaEntrega::deEmpresa($user->empresa_id)->orderBy('orden')->orderBy('id')->get(['id', 'nombre', 'zona'])
                : [],
        ]);
    }

    private function filtrarCuando(Builder $q, string $cuando, ?string $fecha = null): Builder
    {
        $hoy = Carbon::today();

        return match ($cuando) {
            'hoy'       => $q->whereDate('ventas.entrega_programada', $hoy),
            'manana'    => $q->whereDate('ventas.entrega_programada', $hoy->copy()->addDay()),
            'atrasados' => $q->where('ventas.entrega_programada', '<', $hoy),
            'sin_fecha' => $q->whereNull('ventas.entrega_programada'),
            'fecha'     => $q->whereDate('ventas.entrega_programada', $fecha),
            default     => $q,
        };
    }

    public function confirmar(Request $request, ClienteAnticipo $anticipo)
    {
        $user = $request->user();

        abort_if($anticipo->empresa_id !== $user->empresa_id, 403);
        abort_unless($anticipo->estado === 'activo', 422, 'El despacho no está activo.');
        abort_unless($anticipo->tipo_valorizacion === 'material' && $anticipo->venta_id, 422,
            'Solo se pueden confirmar despachos de mercadería vendida.');

        // Un almacenero asignado a un local solo despacha de su local.
        if ($user->local_id && $anticipo->venta?->local_id !== $user->local_id) {
            abort(403, 'No tienes acceso a despachos de otro local.');
        }

        $this->entregas->aplicarEntregaMaterial($anticipo, $request->all(), $user);

        $entregaId = $anticipo->aplicaciones()->latest('id')->value('id');

        return back()
            ->with('success', "Despacho {$anticipo->venta?->numero} confirmado. El stock salió del almacén.")
            // One-shot: la pantalla imprime el ticket de ESTA entrega si se pidió.
            ->with('despacho_entrega', $request->boolean('imprimir') ? $entregaId : null);
    }

    /** Ticket de una entrega, para imprimirlo (o reimprimirlo) desde Despachos. */
    public function ticket(Request $request, ClienteAnticipoAplicacion $entrega)
    {
        $user = $request->user();
        abort_if($entrega->empresa_id !== $user->empresa_id, 403);

        $localId = $entrega->anticipo?->venta?->local_id;
        abort_if($user->local_id && $localId && $localId !== $user->local_id, 403);

        return response()->json(app(TicketPrintService::class)->payloadDeEntregaAnticipo($entrega, $user));
    }
}
