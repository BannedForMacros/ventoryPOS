<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\Turno;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Arma el payload de impresión de tickets para el agente local
 * VentoryPrint.exe (escucha en http://127.0.0.1:9111 en la PC de cada caja).
 *
 * CONTRATO: las claves de este array deben coincidir con TicketPayload en
 * resources/js/lib/ticketPrinter.ts y con Ticket.cs del agente. No renombrar
 * sin actualizar ambos lados.
 */
class TicketPrintService
{
    /** Etiqueta imprimible por tipo de comprobante del POS. */
    private const TIPOS_DOCUMENTO = [
        'ticket'  => 'NOTA DE VENTA',
        'boleta'  => 'BOLETA DE VENTA',
        'factura' => 'FACTURA',
    ];

    /**
     * Etiqueta cuando la venta SÍ tiene comprobante electrónico emitido, por
     * código del catálogo 01 de SUNAT (venta_comprobantes.tipo). Este ticket de
     * 80 mm ES la representación impresa del comprobante (decisión §10.2 del
     * plan de integración: no se imprimen dos documentos).
     */
    private const TIPOS_DOCUMENTO_ELECTRONICO = [
        '01' => 'FACTURA ELECTRÓNICA',
        '03' => 'BOLETA DE VENTA ELECTRÓNICA',
    ];

    /** Leyenda legal obligatoria al pie de la representación impresa. */
    private const LEYENDA_CPE = [
        'Representación impresa del comprobante electrónico.',
        'Consulte en www.sunat.gob.pe',
    ];

    /**
     * Plantilla del ticket con defaults. El admin la edita en Configuración →
     * Empresas (columna empresas.ticket_config); aquí se decide QUÉ se manda al
     * agente, así los cambios de plantilla no requieren recompilar el exe.
     */
    private function configTicket(?Empresa $empresa): array
    {
        $cfg = $empresa?->ticket_config;

        return array_merge([
            'cliente_celular'   => true,
            'cliente_direccion' => true,
            'pie'               => null,
            'lineas_extra'      => [],
        ], is_array($cfg) ? $cfg : []);
    }

    /** Pie del ticket de VENTA: texto configurado (o el default) + líneas extra. */
    private function pieVenta(array $cfg): string
    {
        $pie = trim((string) ($cfg['pie'] ?? '')) ?: 'Gracias por su preferencia';

        return $this->conLineasExtra($cfg, $pie);
    }

    /** Añade las líneas extra configuradas al final de un pie ya armado. */
    private function conLineasExtra(array $cfg, string $pie): string
    {
        $extras = implode("\n", array_filter((array) ($cfg['lineas_extra'] ?? [])));

        return trim($pie . ($extras !== '' ? "\n" . $extras : ''));
    }

    /** Bloque cliente del payload aplicando la plantilla (qué campos salen). */
    private function clientePayload(?\App\Models\Cliente $cliente, string $nombreCli, ?string $docCli, array $cfg): array
    {
        return [
            'nombre'    => $nombreCli !== '' ? $nombreCli : 'Cliente Varios',
            'doc'       => $docCli,
            // null = el agente no imprime la línea; la plantilla decide.
            'direccion' => ($cfg['cliente_direccion'] ?? true) ? ($cliente?->direccion ?: null) : null,
            'telefono'  => ($cfg['cliente_celular'] ?? true) ? ($cliente?->telefono ?: null) : null,
        ];
    }

    /**
     * Construye el ticket imprimible de una venta.
     * El token enruta el ticket a la impresora de la caja correcta: el agente
     * solo imprime si coincide con el token configurado en esa PC.
     */
    public function payloadDeVenta(Venta $venta): array
    {
        $venta->loadMissing([
            'empresa', 'local', 'caja', 'turno.caja', 'user', 'cliente',
            'items', 'pagos.metodoPago.tipo',
        ]);

        $empresa = $venta->empresa;
        $local   = $venta->local;
        // La caja directa de la venta; si falta (ventas antiguas), la del turno.
        $caja    = $venta->caja ?? $venta->turno?->caja;
        $cfg     = $this->configTicket($empresa);

        // ── Comprobante electrónico (V9) ────────────────────────────────────
        // null salvo que la venta tenga un CPE emitido/en emisión. Si es null,
        // TODO lo de abajo queda exactamente igual que antes de la integración.
        $cpe = $this->comprobanteElectronico($venta);

        // Con CPE los datos del adquirente son parte del comprobante: la
        // dirección se imprime aunque la plantilla la tenga desactivada.
        if ($cpe) {
            $cfg['cliente_direccion'] = true;
        }

        // ── Cliente ─────────────────────────────────────────────────────────
        // nombre_completo = razon_social o "nombres apellidos" (accessor del modelo).
        $cliente   = $venta->cliente;
        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        // ── Pagos ───────────────────────────────────────────────────────────
        // Efectivo se detecta por el slug del tipo del método (catálogo
        // tipos_metodo_pago), igual que en Turno::calcularMontoEsperado().
        // En venta_pagos, `monto` es lo ENTREGADO por el cliente y `vuelto`
        // el cambio devuelto (ver VentaService::crear).
        $pagos       = $venta->pagos;
        $hayEfectivo = $pagos->contains(fn ($p) => $p->metodoPago?->tipo?->slug === 'efectivo');
        $metodo      = $pagos->map(fn ($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(' + ');
        $vuelto      = round((float) $pagos->sum('vuelto'), 2);

        return [
            'token' => (string) ($venta->caja?->token_impresora
                ?? $venta->turno?->caja?->token_impresora
                ?? ''),

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                // Con CPE manda el nombre oficial del comprobante ("BOLETA DE
                // VENTA ELECTRÓNICA"); sin CPE, la etiqueta interna de siempre.
                'tipo'     => $cpe
                    ? (self::TIPOS_DOCUMENTO_ELECTRONICO[(string) $cpe->tipo] ?? 'COMPROBANTE ELECTRÓNICO')
                    : (self::TIPOS_DOCUMENTO[$venta->tipo_comprobante] ?? 'NOTA DE VENTA'),
                // `serie` se mantiene en null: el agente ya imprime el número
                // completo y `numero` del CPE viene con la serie ("B002-00000123").
                'serie'    => null,
                'numero'   => $cpe && $cpe->numero ? (string) $cpe->numero : $venta->numero,
                'fecha'    => $venta->fecha_venta?->format('d/m/Y h:i A'),
                'vendedor' => $venta->user?->name,
                'caja'     => $caja?->nombre,
            ],

            'cliente' => $this->clientePayload($cliente, $nombreCli, $docCli, $cfg),

            'items' => $venta->items->map(fn ($item) => [
                'cant'    => (float) $item->cantidad,
                'desc'    => $item->producto_nombre,
                'precio'  => (float) $item->precio_unitario,
                // subtotal del item = (precio - descuento_item) * cantidad
                'importe' => (float) $item->subtotal,
                'unidad'  => $item->unidad_nombre,
            ])->values()->all(),

            'totales' => [
                'subtotal'  => (float) $venta->subtotal,
                'igv'       => (float) $venta->igv,
                'descuento' => (float) $venta->descuento_total,
                'total'     => (float) $venta->total,
                'moneda'    => $venta->moneda ?? 'PEN',
            ],

            'pago' => [
                'metodo'   => $metodo !== '' ? $metodo : null,
                'recibido' => $hayEfectivo ? round((float) $pagos->sum('monto'), 2) : null,
                'vuelto'   => $vuelto > 0 ? $vuelto : null,
            ],

            // Con CPE el pie lleva además la leyenda legal, el hash y la
            // referencia interna. Sin CPE es el pie de siempre, byte a byte.
            'pie'        => $cpe
                ? $this->pieComprobanteElectronico($cpe, $venta, $this->pieVenta($cfg))
                : $this->pieVenta($cfg),
            // El QR SUNAT ya viene armado desde el emisor; el agente lo imprime
            // en ESC/POS. Sin CPE sigue siendo null (no se imprime QR).
            'qr'         => $cpe && trim((string) $cpe->qr) !== '' ? (string) $cpe->qr : null,
            // Abrir el cajón portamonedas solo cuando entró efectivo.
            'abrirCajon' => $hayEfectivo,
            'copias'     => 1,
            // Logo de la empresa en base64 (sale si el agente lo soporta).
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Construye el ticket imprimible de una COTIZACIÓN (proforma). Espejo de
     * payloadDeVenta: mismo contrato de claves, pero sin datos de pago (aún no
     * se cobra) y con el pie como proforma.
     */
    public function payloadDeCotizacion(Cotizacion $cot, ?\App\Models\User $user = null): array
    {
        $cot->loadMissing(['empresa', 'local', 'user', 'cliente', 'items']);

        $empresa = $cot->empresa;
        $local   = $cot->local;
        $cfg     = $this->configTicket($empresa);

        // Cliente (nombre_completo = razon_social o "nombres apellidos").
        $cliente   = $cot->cliente;
        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        // La cotización no tiene caja propia: enrutamos a la ticketera de la caja
        // del turno abierto del usuario que imprime (SU PC). Ver resolverTokenImpresora.
        $token = $this->resolverTokenImpresora($user, $cot->local_id);

        $validez = $cot->fecha_vencimiento
            ? Carbon::parse($cot->fecha_vencimiento)->format('d/m/Y')
            : null;

        $pie = trim(
            ($cot->observacion ? $cot->observacion . "\n" : '')
            . 'Proforma — no es comprobante de pago'
            . ($validez ? "\nVálida hasta {$validez}" : '')
        );

        return [
            'token' => $token,

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                'tipo'     => 'COTIZACIÓN / PROFORMA',
                'serie'    => null,
                'numero'   => $cot->numero,
                'fecha'    => $cot->fecha ? Carbon::parse($cot->fecha)->format('d/m/Y') : null,
                'vendedor' => $cot->user?->name,
                'caja'     => null,
            ],

            'cliente' => $this->clientePayload($cliente, $nombreCli, $docCli, $cfg),

            'items' => $cot->items->map(fn ($item) => [
                'cant'    => (float) $item->cantidad,
                'desc'    => $item->producto_nombre,
                'precio'  => (float) $item->precio_unitario,
                'importe' => (float) $item->subtotal,
                'unidad'  => $item->unidad_nombre,
            ])->values()->all(),

            'totales' => [
                'subtotal'  => (float) $cot->subtotal,
                'igv'       => (float) $cot->igv,
                'descuento' => (float) $cot->descuento_total,
                'total'     => (float) $cot->total,
                'moneda'    => 'PEN',
            ],

            // Una cotización no lleva pago: se cobra al convertirla en venta.
            'pago' => [
                'metodo'   => null,
                'recibido' => null,
                'vuelto'   => null,
            ],

            'pie'        => $this->conLineasExtra($cfg, $pie),
            'qr'         => null,
            'abrirCajon' => false,
            'copias'     => 1,
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Payload para el ticket de una ENTREGA de anticipo (constancia de entrega
     * de mercadería pagada por adelantado). Mismo contrato de claves.
     */
    public function payloadDeEntregaAnticipo(ClienteAnticipoAplicacion $entrega, ?\App\Models\User $user = null): array
    {
        $entrega->loadMissing([
            'anticipo.empresa', 'anticipo.cliente', 'anticipo.venta.local',
            'user', 'items.item',
        ]);

        $anticipo = $entrega->anticipo;
        $empresa  = $anticipo?->empresa;
        $local    = $anticipo?->venta?->local;
        $cliente  = $anticipo?->cliente;
        $cfg      = $this->configTicket($empresa);

        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        // Ticketera de la caja del turno abierto del usuario que imprime (SU PC),
        // con fallback a la primera caja del local. Ver resolverTokenImpresora.
        $localId = $anticipo?->venta?->local_id;
        $token   = $this->resolverTokenImpresora($user, $localId);

        $items = $entrega->items->map(fn ($ai) => [
            'cant'    => (float) $ai->cantidad,
            'desc'    => $ai->item?->producto_nombre ?? 'Producto',
            'precio'  => (float) ($ai->item?->precio_unitario ?? 0),
            'importe' => round((float) $ai->cantidad * (float) ($ai->item?->precio_unitario ?? 0), 2),
            'unidad'  => $ai->item?->unidad_nombre ?? 'und',
        ])->values()->all();

        // Total: valor de lo entregado (si hay ítems) o el monto de la aplicación.
        $total = count($items) > 0 ? array_sum(array_column($items, 'importe')) : (float) $entrega->monto;

        $pie = trim(
            ($entrega->observacion ? $entrega->observacion . "\n" : '')
            . 'Constancia de ENTREGA de mercadería (anticipo)'
            . ($anticipo?->venta?->numero ? "\nVenta origen: {$anticipo->venta->numero}" : '')
        );

        return [
            'token' => $token,

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                'tipo'     => 'ENTREGA DE ANTICIPO',
                'serie'    => null,
                'numero'   => $entrega->numero,
                'fecha'    => $entrega->fecha ? Carbon::parse($entrega->fecha)->format('d/m/Y') : null,
                'vendedor' => $entrega->user?->name,
                'caja'     => null,
            ],

            'cliente' => $this->clientePayload($cliente, $nombreCli, $docCli, $cfg),

            'items' => $items,

            'totales' => [
                'subtotal'  => round($total, 2),
                'igv'       => 0.0,
                'descuento' => 0.0,
                'total'     => round($total, 2),
                'moneda'    => 'PEN',
            ],

            'pago' => [
                'metodo'   => null,
                'recibido' => null,
                'vuelto'   => null,
            ],

            'pie'        => $this->conLineasExtra($cfg, $pie),
            'qr'         => null,
            'abrirCajon' => false,
            'copias'     => 1,
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Comprobante electrónico de la venta, o null.
     *
     * Deliberadamente defensivo: la relación y la tabla `venta_comprobantes`
     * son parte de la integración SUNAT y pueden no existir todavía (módulo
     * apagado, migración sin correr, despliegue parcial). Ante CUALQUIER
     * problema devuelve null y el ticket sale exactamente como hoy: imprimir
     * nunca puede fallar por la facturación electrónica.
     */
    private function comprobanteElectronico(Venta $venta): ?object
    {
        // Un `ticket` es nota de venta interna: nunca tiene CPE. Cortar acá deja
        // el flujo actual de ferretería sin una sola consulta extra.
        if ($venta->tipo_comprobante === 'ticket') {
            return null;
        }

        if (!method_exists($venta, 'comprobanteElectronico')) {
            return null;
        }

        try {
            $cpe = $venta->comprobanteElectronico;
        } catch (\Throwable $e) {
            return null;
        }

        // Sin número oficial no hay nada que representar: se imprime como hoy.
        return ($cpe && trim((string) $cpe->numero) !== '') ? $cpe : null;
    }

    /**
     * Pie de la representación impresa: el pie configurado de siempre + la
     * leyenda legal SUNAT, el hash del CPE (si ya se conoce) y el número
     * interno de la venta, que sigue siendo la referencia para devoluciones.
     */
    private function pieComprobanteElectronico(object $cpe, Venta $venta, string $pieBase): string
    {
        $lineas = self::LEYENDA_CPE;

        $hash = trim((string) ($cpe->hash_cpe ?? ''));
        if ($hash !== '') {
            $lineas[] = 'Hash: ' . $hash;
        }

        $lineas[] = 'Ref. interna: ' . $venta->numero;

        return trim(implode("\n", $lineas) . ($pieBase !== '' ? "\n" . $pieBase : ''));
    }

    /**
     * Token de impresora para tickets SIN caja propia (cotización, entrega de
     * anticipo). Prioriza la caja del turno ABIERTO del usuario que imprime —es
     * decir, la ticketera de SU PC—; si no tiene turno, cae a la primera caja del
     * local. Antes se usaba siempre "la primera caja del local", lo que enviaba el
     * token de OTRA PC cuando el local tiene varias cajas y el agente lo rechazaba
     * con "Token invalido: este ticket no corresponde a la caja configurada...".
     */
    private function resolverTokenImpresora(?\App\Models\User $user, ?int $localId): string
    {
        if ($user) {
            $token = (string) (\App\Models\Turno::turnoActivoDelUsuario($user->id)?->caja?->token_impresora ?? '');
            if ($token !== '') {
                return $token;
            }
        }
        if (!$localId) {
            return '';
        }
        return (string) (Caja::where('local_id', $localId)->get()
            ->pluck('token_impresora')->filter()->first() ?? '');
    }

    /**
     * Logo de la empresa como data URI base64 (disco 'public'), o null si no hay
     * logo o el archivo no existe. png/jpg/webp detectado por la extensión.
     */
    private function logoBase64(?Empresa $empresa): ?string
    {
        $path = $empresa?->logo;
        if (!$path) {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
            $bytes = Storage::disk('public')->get($path);
        } catch (\Throwable $e) {
            return null;
        }

        $ext  = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Arma el payload del reporte de cierre de turno para el agente local.
     * Contrato: las claves deben coincidir con ShiftClosurePayload en
     * resources/js/lib/ticketPrinter.ts y con ShiftClosurePayload.cs del agente.
     */
    public function payloadDeCierreTurno(Turno $turno): array
    {
        $turno->loadMissing([
            'empresa', 'local', 'caja', 'user', 'userCierre',
            'ventas' => fn ($q) => $q->where('estado', 'completada')->with('pagos.metodoPago.tipo'),
            'gastos', 'retiros', 'arqueo', 'arqueoMetodos.metodoPago',
        ]);

        $empresa = $turno->empresa;
        $local   = $turno->local;
        $caja    = $turno->caja;
        $cfg     = $this->configTicket($empresa);

        // ── Resumen de ventas y desglose por método de pago ─────────────────
        $metodos = [];
        $totalVentas = 0.0;
        $subtotal    = 0.0;
        $igv         = 0.0;
        $descuento   = 0.0;
        $ventasEfectivo = 0.0;

        foreach ($turno->ventas as $venta) {
            $totalVentas += (float) $venta->total;
            $subtotal    += (float) $venta->subtotal;
            $igv         += (float) $venta->igv;
            $descuento   += (float) $venta->descuento_total;

            foreach ($venta->pagos as $pago) {
                $nombre = $pago->metodoPago?->nombre ?? 'Otro';
                $monto  = (float) $pago->monto;

                if (!isset($metodos[$nombre])) {
                    $metodos[$nombre] = ['monto' => 0.0, 'cantidad' => 0];
                }
                $metodos[$nombre]['monto'] += $monto;
                $metodos[$nombre]['cantidad']++;

                if ($pago->metodoPago?->tipo?->slug === 'efectivo') {
                    $ventasEfectivo += $monto;
                }
            }
        }

        $metodosPago = collect($metodos)
            ->map(fn ($m, $nombre) => [
                'nombre'   => $nombre,
                'monto'    => round($m['monto'], 2),
                'cantidad' => $m['cantidad'],
            ])
            ->sortByDesc('monto')
            ->values()
            ->all();

        // ── Consolidado de caja (efectivo) ──────────────────────────────────
        $salidas = (float) $turno->retiros()->sum('monto');

        $montoEsperado  = $turno->monto_cierre_esperado !== null
            ? (float) $turno->monto_cierre_esperado
            : $turno->calcularMontoEsperado();
        $montoDeclarado = $turno->monto_cierre_declarado !== null
            ? (float) $turno->monto_cierre_declarado
            : null;
        $diferencia = $turno->diferencia !== null
            ? (float) $turno->diferencia
            : null;

        $pie = trim((string) ($cfg['pie'] ?? ''));
        $pie = $pie !== ''
            ? $pie . "\nReporte de cierre de turno"
            : 'Reporte de cierre de turno';
        $pie = $this->conLineasExtra($cfg, $pie);

        return [
            'token' => (string) ($caja?->token_impresora ?? ''),
            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],
            'turno' => [
                'id'            => (string) $turno->id,
                'nombre'        => 'Turno #' . $turno->id,
                'cajero'        => $turno->user?->name,
                'caja'          => $caja?->nombre,
                'fechaApertura' => $turno->fecha_apertura?->format('d/m/Y h:i A'),
                'fechaCierre'   => $turno->fecha_cierre?->format('d/m/Y h:i A'),
            ],
            'resumen' => [
                'numeroVentas' => $turno->ventas->count(),
                'subtotal'     => round($subtotal, 2),
                'igv'          => round($igv, 2),
                'descuento'    => round($descuento, 2),
                'total'        => round($totalVentas, 2),
                'moneda'       => 'PEN',
            ],
            'metodosPago' => $metodosPago,
            'caja' => [
                'montoApertura'      => (float) $turno->monto_apertura,
                'ventasEfectivo'     => round($ventasEfectivo, 2),
                'entradas'           => 0.0,
                'salidas'            => round($salidas, 2),
                'efectivoEsperado'   => round($montoEsperado, 2),
                'efectivoDeclarado'  => $montoDeclarado !== null ? round($montoDeclarado, 2) : null,
                'diferencia'         => $diferencia !== null ? round($diferencia, 2) : null,
            ],
            'pie'    => $pie,
            'logo'   => $this->logoBase64($empresa),
            'copias' => 1,
        ];
    }
}
