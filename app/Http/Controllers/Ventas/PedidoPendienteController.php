<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Turno;
use App\Models\Venta;
use App\Services\ModificarPedidoPendienteService;
use App\Support\ExigeCuentaDePago;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Modificar pedido": el cliente vuelve días después y cambia lo que dejó
 * pendiente por entregar. Toda la lógica vive en ModificarPedidoPendienteService.
 */
class PedidoPendienteController extends Controller
{
    use ExigeCuentaDePago;

    public function __construct(private ModificarPedidoPendienteService $service) {}

    /** Datos para abrir el modal (pendientes, precios, anticipos, métodos, turnos). */
    public function datos(Request $request, Venta $venta)
    {
        $user = $request->user();
        abort_if($venta->empresa_id !== $user->empresa_id, 403);

        return response()->json($this->service->datos($venta, $user) + [
            'metodos_pago' => MetodoPago::deEmpresa($user->empresa_id)->activo()
                ->with(['tipo:id,slug', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])
                ->orderBy('nombre')->get()
                ->map(fn ($m) => [
                    'id' => $m->id, 'nombre' => $m->nombre, 'tipo_slug' => $m->tipo?->slug,
                    'cuentas' => $m->cuentas->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values(),
                ]),
            'cuentas' => Cuenta::deEmpresa($user->empresa_id)->activo()
                ->orderByDesc('es_efectivo')->orderBy('nombre')->get(['id', 'nombre', 'es_efectivo']),
            'turnos' => Turno::deEmpresa($user->empresa_id)
                ->with(['user:id,name', 'caja:id,nombre'])
                ->where('estado', 'abierto')
                ->orderByDesc('fecha_apertura')->limit(40)
                ->get(['id', 'user_id', 'caja_id', 'fecha_apertura', 'estado']),
        ]);
    }

    public function modificar(Request $request, Venta $venta)
    {
        $user = $request->user();
        abort_if($venta->empresa_id !== $user->empresa_id, 403);

        $data = $request->validate([
            'motivo'                        => ['required', 'string', 'min:5', 'max:500'],
            'items'                         => ['nullable', 'array'],
            'items.*.id'                    => ['required', 'integer'],
            'items.*.cantidad_pendiente'    => ['required', 'numeric', 'min:0'],
            // Precio del pendiente: sugerido el de la venta, pero editable.
            'items.*.precio_unitario'       => ['nullable', 'numeric', 'min:0'],
            'nuevos'                        => ['nullable', 'array', 'max:50'],
            'nuevos.*.producto_id'          => ['required', 'integer'],
            'nuevos.*.producto_unidad_id'   => ['required', 'integer'],
            'nuevos.*.cantidad'             => ['required', 'numeric', 'min:0.0001'],
            'nuevos.*.precio_unitario'      => ['required', 'numeric', 'min:0'],
            // Si falta dinero
            'cobro_anticipo_id'             => ['nullable', 'integer', Rule::exists('cliente_anticipos', 'id')->where('empresa_id', $user->empresa_id)],
            'cobro_monto_anticipo'          => ['nullable', 'numeric', 'min:0'],
            'cobro_monto_pago'              => ['nullable', 'numeric', 'min:0'],
            'dejar_credito'                 => ['nullable', 'boolean'],
            // Si sobra dinero
            'excedente_destino'             => ['nullable', Rule::in(['saldo_favor', 'devolver'])],
            // Método/cuenta/turno del dinero que se mueve HOY (cobro o devolución)
            'metodo_pago_id'                => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'                     => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id), $this->reglaCuentaObligatoria($request)],
            'referencia'                    => ['nullable', 'string', 'max:200'],
            'turno_id'                      => ['nullable', 'integer'],
        ]);

        $r = $this->service->modificar($venta, $data, $user);

        $liq = $r['liquidacion'];
        $msj = "Pedido de la venta {$venta->numero} modificado.";
        $msj .= match ($liq['tipo']) {
            'cobro'       => ' Cobrado hoy: S/ ' . number_format($liq['del_anticipo'] + $liq['pago'], 2)
                . ($liq['al_credito'] > 0.009 ? ' · Al crédito: S/ ' . number_format($liq['al_credito'], 2) : ''),
            'saldo_favor' => ' Quedaron S/ ' . number_format($liq['excedente'], 2) . ' a favor del cliente (anticipo #' . $liq['anticipo_id'] . ').',
            'devolver'    => ' Se devolvieron S/ ' . number_format($liq['excedente'], 2) . ' al cliente.',
            default       => ' Sin diferencia de dinero.',
        };

        return back()->with('success', $msj);
    }
}
