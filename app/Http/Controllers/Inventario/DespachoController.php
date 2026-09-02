<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ClienteAnticipo;
use App\Services\EntregaPendienteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Bandeja del almacenero: mercadería vendida que quedó pendiente de despacho
 * (despacho en almacén). El stock no salió al vender; sale cuando el almacenero
 * confirma la entrega aquí.
 */
class DespachoController extends Controller
{
    public function __construct(
        private EntregaPendienteService $entregas,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ClienteAnticipo::deEmpresa($user->empresa_id)
            ->where('tipo_valorizacion', 'material')
            ->where('estado', 'activo')
            ->whereNotNull('venta_id')
            ->with([
                'cliente:id,nombres,apellidos,razon_social',
                'user:id,name',
                'venta:id,numero,local_id,fecha_venta',
                'venta.local:id,nombre',
                'items.producto:id,nombre,precio_venta',
                'items.unidad:id,precio_venta',
            ])
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('observacion', 'ilike', "%{$t}%")
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombres', 'ilike', "%{$t}%")
                        ->orWhere('apellidos', 'ilike', "%{$t}%")
                        ->orWhere('razon_social', 'ilike', "%{$t}%"))
                    ->orWhereHas('venta', fn ($v) => $v->where('numero', 'ilike', "%{$t}%"))
                    ->orWhereHas('items.producto', fn ($p) => $p->where('nombre', 'ilike', "%{$t}%")));
            })
            ->when($user->local_id, fn ($q) => $q->whereHas('venta', fn ($v) => $v->where('local_id', $user->local_id)))
            ->orderByDesc('created_at');

        $pendientes = $query->paginate(25)->withQueryString();

        return Inertia::render('Despachos/Index', [
            'pendientes' => $pendientes,
            'buscar'     => $request->input('buscar', ''),
        ]);
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

        return redirect()->route('despachos.index')
            ->with('success', "Despacho {$anticipo->venta?->numero} confirmado. El stock salió del almacén.");
    }
}
