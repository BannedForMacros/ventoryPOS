<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Local;
use App\Services\AuditoriaService;
use App\Services\TicketBloquesService;
use App\Support\PlantillaTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Plantilla del ticket impreso de la empresa: qué plantilla usa, qué secciones
 * salen, en qué orden y con qué textos, con vista previa. La vista previa usa
 * el mismo armado de bloques que la impresión real, con una venta de muestra.
 */
class TicketPlantillaController extends Controller
{
    public function __construct(private TicketBloquesService $bloques) {}

    public function index(Request $request)
    {
        $empresa = $request->user()->empresa;
        $plantilla = PlantillaTicket::resolver($empresa->ticket_plantilla);

        return Inertia::render('Configuracion/TicketPlantilla', [
            'plantilla'       => $plantilla,
            'catalogo'        => PlantillaTicket::catalogo(),
            'posDatosCliente' => (bool) $empresa->pos_datos_cliente,
            // Siempre con la detallada: sirve para verla antes de elegirla.
            'vistaPrevia'     => $this->muestras($empresa, ['plantilla' => PlantillaTicket::DETALLADA] + $plantilla),
            // El ticket de siempre, con los mismos datos de muestra.
            'vistaEstandar'   => $this->muestrasEstandar($empresa),
            'puedeEditar'     => $request->user()->tienePermiso('config.ticket', 'editar'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validar($request) + $request->validate(['pos_datos_cliente' => ['required', 'boolean']]);
        $empresa = $request->user()->empresa;

        $empresa->update([
            // Se guarda ya resuelta: completa y sin claves desconocidas.
            'ticket_plantilla'  => PlantillaTicket::resolver($data),
            'pos_datos_cliente' => $data['pos_datos_cliente'],
        ]);

        AuditoriaService::log('ticket.plantilla_actualizada', $empresa, [
            'plantilla' => $data['plantilla'],
        ], $request->user());

        return back()->with('success', 'Plantilla del ticket guardada. Los próximos tickets ya salen así.');
    }

    /** Vista previa de una plantilla que todavía no se guardó. */
    public function vistaPrevia(Request $request)
    {
        $plantilla = PlantillaTicket::resolver(['plantilla' => PlantillaTicket::DETALLADA] + $this->validar($request));

        return response()->json(['vistaPrevia' => $this->muestras($request->user()->empresa, $plantilla)]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'plantilla'          => ['required', Rule::in(array_keys(PlantillaTicket::PLANTILLAS))],
            'secciones'          => ['nullable', 'array', 'max:30'],
            'secciones.*.clave'  => ['required', 'string', 'max:40'],
            'secciones.*.activa' => ['required', 'boolean'],
            'textos'             => ['nullable', 'array'],
            'textos.*'           => ['nullable', 'string', 'max:60'],
            'opciones'           => ['nullable', 'array'],
            'opciones.*'         => ['boolean'],
        ]);
    }

    /**
     * Tickets de muestra con los datos reales del negocio: una venta pagada,
     * una con saldo por cobrar y una cotización.
     */
    private function muestras(Empresa $empresa, array $plantilla): array
    {
        [$base, $conSaldo, $cotizacion] = $this->ticketsDeMuestra($empresa);
        $extras = ['cajero_telefono' => '974 123 456', 'observacion' => self::OBSERVACION];

        return [
            ['clave' => 'pagada', 'nombre' => 'Venta pagada', 'bloques' => $this->bloques->armar($base, $extras + [
                'doc' => 'venta', 'saldo' => 0, 'a_cuenta' => 972.5,
                'pagos' => [['nombre' => 'Efectivo', 'monto' => 472.5], ['nombre' => 'Yape', 'monto' => 500]],
            ], $plantilla)],
            ['clave' => 'saldo', 'nombre' => 'Venta con saldo', 'bloques' => $this->bloques->armar($conSaldo, $extras + [
                'doc' => 'venta', 'saldo' => 572.5, 'a_cuenta' => 400, 'vencimiento' => now()->addDays(15)->format('d/m/Y'),
                'pagos' => [['nombre' => 'Yape', 'monto' => 400]],
            ], $plantilla)],
            ['clave' => 'cotizacion', 'nombre' => 'Cotización', 'bloques' => $this->bloques->armar($cotizacion, $extras + [
                'doc' => 'cotizacion',
                'pie' => $this->leyendaProforma(),
            ], $plantilla)],
        ];
    }

    /**
     * Las mismas muestras como las recibe el agente en el ticket estándar: sin
     * bloques. La pantalla las dibuja con el diseño de siempre.
     */
    private function muestrasEstandar(Empresa $empresa): array
    {
        [$base, $conSaldo, $cotizacion] = $this->ticketsDeMuestra($empresa);

        // En el estándar la observación de la cotización va en el pie.
        $cotizacion['pie'] = trim(self::OBSERVACION . "\n" . $this->leyendaProforma() . "\n" . $this->lineasExtra($empresa));
        $cotizacion['pago'] = ['metodo' => null, 'recibido' => null, 'vuelto' => null];

        return [
            ['clave' => 'pagada', 'nombre' => 'Venta pagada', 'ticket' => $base],
            ['clave' => 'saldo', 'nombre' => 'Venta con saldo', 'ticket' => $conSaldo],
            ['clave' => 'cotizacion', 'nombre' => 'Cotización', 'ticket' => $cotizacion],
        ];
    }

    private const OBSERVACION = 'Dejar el material por la puerta lateral';

    private function leyendaProforma(): string
    {
        return "Proforma — no es comprobante de pago\nVálida hasta " . now()->addDays(7)->format('d/m/Y');
    }

    private function lineasExtra(Empresa $empresa): string
    {
        return implode("\n", array_filter((array) (((array) $empresa->ticket_config)['lineas_extra'] ?? [])));
    }

    /** @return array{0: array, 1: array, 2: array} venta pagada, venta con saldo y cotización */
    private function ticketsDeMuestra(Empresa $empresa): array
    {
        $cfg = array_merge([
            'mostrar_ruc' => true, 'mostrar_igv' => false, 'mostrar_cajero' => true, 'mostrar_caja' => true,
            'cliente_celular' => true, 'cliente_direccion' => true,
        ], (array) $empresa->ticket_config);

        $local = Local::where('empresa_id', $empresa->id)->orderBy('id')->first();
        $pie = trim((trim((string) ($cfg['pie'] ?? '')) ?: 'Gracias por su preferencia') . "\n" . $this->lineasExtra($empresa));

        $base = [
            'negocio' => [
                'nombre'     => $empresa->nombre_comercial ?: $empresa->razon_social,
                'ruc'        => $empresa->ruc,
                'direccion'  => $local?->direccion ?: $empresa->direccion,
                'telefono'   => $local?->telefono ?: $empresa->telefono,
                'mostrarRuc' => (bool) $cfg['mostrar_ruc'],
                'mostrarIgv' => (bool) $cfg['mostrar_igv'],
            ],
            'documento' => [
                'tipo'     => 'NOTA DE VENTA',
                'numero'   => 'V-0021',
                'fecha'    => now()->format('d/m/Y h:i A'),
                'vendedor' => $cfg['mostrar_cajero'] ? 'María Torres' : null,
                'caja'     => $cfg['mostrar_caja'] ? 'Caja 1' : null,
            ],
            'cliente' => [
                'nombre'    => 'Dagoberto Rodríguez',
                'doc'       => 'DNI 16789012',
                'telefono'  => $cfg['cliente_celular'] ? '979 555 012' : null,
                'direccion' => $cfg['cliente_direccion'] ? 'Calle Los Cedros 245, Pomalca' : null,
            ],
            'items' => [
                ['cant' => 20, 'desc' => 'Cemento Azul Pacasmayo Antisalitre', 'precio' => 35, 'importe' => 700, 'unidad' => 'Und'],
                ['cant' => 5, 'desc' => 'Arena Amarilla', 'precio' => 52, 'importe' => 260, 'unidad' => 'm3'],
                ['cant' => 2.5, 'desc' => 'Alambre Negro N° 8', 'precio' => 5, 'importe' => 12.5, 'unidad' => 'Kg'],
            ],
            'totales' => ['subtotal' => 972.5, 'igv' => 0, 'descuento' => 0, 'total' => 972.5, 'moneda' => 'PEN'],
            'pago'    => ['metodo' => 'Efectivo + Yape', 'recibido' => 500, 'vuelto' => 27.5],
            'pie'     => $pie,
            'qr'      => null,
            'logo'    => $empresa->logo ? 'logo' : null,
        ];

        $conSaldo = $base;
        $conSaldo['pago'] = ['metodo' => 'Yape', 'recibido' => null, 'vuelto' => null];

        $cotizacion = $base;
        $cotizacion['documento'] = [
            'tipo' => 'COTIZACIÓN / PROFORMA', 'numero' => 'COT-0516', 'fecha' => now()->format('d/m/Y'),
            'vendedor' => $base['documento']['vendedor'], 'caja' => null,
        ];

        return [$base, $conSaldo, $cotizacion];
    }
}
