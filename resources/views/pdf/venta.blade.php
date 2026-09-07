@php
    use App\Support\NumeroEnLetras;
    use Illuminate\Support\Carbon;

    /** ── Helpers de formato ─────────────────────────────────────────────── */
    $moneda  = strtoupper($venta->moneda ?? 'PEN');
    $simbolo = $moneda === 'USD' ? 'US$' : 'S/';
    $money   = fn ($v, $sim = null) => ($sim ?? 'S/') . ' ' . number_format((float) $v, 2, '.', ',');
    $cant    = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $fecha   = fn ($f, $formato = 'd/m/Y') => $f ? Carbon::parse($f)->format($formato) : '—';

    /** ── Empresa / local ────────────────────────────────────────────────── */
    $negocio   = $empresa?->nombre_comercial ?: $empresa?->razon_social ?: 'Mi Negocio';
    $direccion = $venta->local?->direccion ?: $empresa?->direccion;
    $telefono  = $venta->local?->telefono ?: $empresa?->telefono;

    /** ── Documento: con CPE manda el nombre oficial ─────────────────────── */
    $tiposDoc = [
        'ticket'          => 'NOTA DE VENTA',
        'boleta'          => 'BOLETA DE VENTA',
        'factura'         => 'FACTURA',
        'boleta_externa'  => 'BOLETA ELECTRÓNICA EXTERNA',
        'factura_externa' => 'FACTURA ELECTRÓNICA EXTERNA',
    ];
    $tiposCpe = ['01' => 'FACTURA ELECTRÓNICA', '03' => 'BOLETA DE VENTA ELECTRÓNICA'];

    $docTipo   = $cpe
        ? ($tiposCpe[(string) $cpe->tipo] ?? 'COMPROBANTE ELECTRÓNICO')
        : ($tiposDoc[$venta->tipo_comprobante] ?? 'NOTA DE VENTA');
    $docNumero = $cpe?->numero ?: ($venta->numero_comprobante ?: $venta->numero);

    /** ── Cliente ────────────────────────────────────────────────────────── */
    $cliente    = $venta->cliente;
    $cliNombre  = trim((string) ($cliente?->nombre_completo ?? '')) ?: 'Cliente general';
    $cliDoc     = ($cliente && $cliente->numero_documento)
        ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
        : null;

    /** ── Datos calculados ───────────────────────────────────────────────── */
    $items    = $venta->items ?? collect();
    $pagos    = $venta->pagos ?? collect();
    $abonos   = $venta->abonos ?? collect();
    $descLogs = $venta->descuentosLog ?? collect();
    $caja     = $venta->caja ?? $venta->turno?->caja;
    $vuelto   = round((float) $pagos->sum('vuelto'), 2);

    // Pendiente por entregar: ítems de anticipos activos con saldo por entregar.
    $pendientes = collect($venta->anticipos ?? [])
        ->where('estado', 'activo')
        ->flatMap(fn ($a) => $a->items ?? collect())
        ->filter(fn ($ai) => (float) $ai->cantidad_pendiente > 0.0001)
        ->values();

    $anulada  = $venta->estado === 'anulada';
    $enLetras = NumeroEnLetras::importe((float) $venta->total, 'PEN');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Venta {{ $venta->numero }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { margin: 34px 38px 58px 38px; }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9.5px; color: #1e293b; line-height: 1.45;
    }
    table { width: 100%; border-collapse: collapse; }
    .r { text-align: right; } .c { text-align: center; }
    .muted { color: #64748b; }
    .small { font-size: 8.5px; }
    .b { font-weight: bold; }

    /* Marca de agua para ventas anuladas */
    .watermark {
        position: fixed; top: 42%; left: 0; width: 100%;
        text-align: center; z-index: -10;
        font-size: 92px; font-weight: bold; color: rgba(220, 38, 38, .12);
        transform: rotate(-24deg);
    }

    /* ── Cabecera ── */
    .head td { vertical-align: top; }
    .logo { max-height: 62px; max-width: 170px; }
    .neg-nombre { font-size: 16px; font-weight: bold; color: #0f172a; }
    .neg-meta { color: #64748b; font-size: 8.8px; }
    .doc-box {
        width: 215px; border: 1.6px solid #0f172a; border-radius: 6px;
        text-align: center; padding: 9px 10px;
    }
    .doc-box .tit { font-size: 10.5px; font-weight: bold; letter-spacing: .6px; color: #0f172a; }
    .doc-box .num { font-size: 13.5px; font-weight: bold; color: #2563eb; margin-top: 2px; }
    .doc-box .ref { font-size: 8px; color: #64748b; margin-top: 3px; }
    .badge-anulada {
        display: inline-block; margin-top: 5px; padding: 2px 10px; border-radius: 9px;
        background: #fee2e2; color: #b91c1c; font-weight: bold; font-size: 8.5px;
    }

    /* ── Cajas de información ── */
    .info-wrap { margin-top: 12px; }
    .info-wrap > tbody > tr > td { vertical-align: top; }
    .box {
        border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; background: #f8fafc;
    }
    .box .label {
        font-size: 7.5px; text-transform: uppercase; letter-spacing: .8px;
        color: #94a3b8; font-weight: bold; margin-bottom: 3px;
    }
    .kv td { padding: 1.2px 0; font-size: 9px; }
    .kv .k { color: #64748b; width: 78px; }

    /* ── Secciones ── */
    .sec-title {
        font-size: 8px; text-transform: uppercase; letter-spacing: 1px;
        color: #2563eb; font-weight: bold; margin: 14px 0 4px 0;
        border-bottom: 1px solid #e2e8f0; padding-bottom: 3px;
    }

    /* ── Tabla de ítems ── */
    .items thead th {
        background: #0f172a; color: #fff; font-size: 7.8px; text-transform: uppercase;
        letter-spacing: .5px; padding: 6px 7px; text-align: left;
    }
    .items thead th.r { text-align: right; }
    .items thead th.c { text-align: center; }
    .items tbody td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .items tbody tr:nth-child(even) td { background: #f8fafc; }
    .tachado { text-decoration: line-through; color: #94a3b8; font-size: 8px; }

    /* ── Totales ── */
    .tots-wrap { margin-top: 10px; }
    .tots-wrap td { vertical-align: top; }
    .letras {
        border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;
        padding: 7px 10px; font-size: 8.6px; color: #334155;
    }
    .tots { width: 235px; }
    .tots td { padding: 2.6px 8px; font-size: 9.5px; }
    .tots .k { color: #64748b; }
    .tots .v { text-align: right; font-weight: bold; }
    .tots .grand td {
        border-top: 1.6px solid #0f172a; padding-top: 6px;
        font-size: 12.5px; font-weight: bold; color: #0f172a;
    }
    .desc-rojo { color: #dc2626; }

    /* ── Pagos / crédito ── */
    .plain thead th {
        background: #f1f5f9; color: #475569; font-size: 7.8px; text-transform: uppercase;
        letter-spacing: .5px; padding: 5px 7px; text-align: left; border-bottom: 1px solid #cbd5e1;
    }
    .plain thead th.r { text-align: right; }
    .plain tbody td { padding: 4.5px 7px; border-bottom: 1px solid #eef2f7; }
    .credito {
        margin-top: 8px; border: 1px solid #fcd34d; background: #fffbeb;
        border-radius: 6px; padding: 7px 10px; font-size: 9px;
    }

    /* ── Observación / CPE ── */
    .obs {
        margin-top: 10px; border-left: 3px solid #2563eb; background: #f8fafc;
        border-radius: 4px; padding: 7px 10px;
    }
    .cpe {
        margin-top: 12px; border: 1px dashed #cbd5e1; border-radius: 6px;
        padding: 7px 10px; font-size: 8.5px; color: #475569;
    }

    /* ── Pie fijo ── */
    .footer {
        position: fixed; bottom: 0; left: 0; width: 100%;
        text-align: center; font-size: 7.8px; color: #94a3b8;
        border-top: 1px solid #e2e8f0; padding-top: 5px;
    }
</style>
</head>
<body>

@if ($anulada)
    <div class="watermark">ANULADA</div>
@endif

{{-- ═══ Cabecera: empresa + caja de documento ═══ --}}
<table class="head">
    <tr>
        <td>
            <table>
                <tr>
                    @if ($logo)
                        <td style="width: 180px; padding-right: 12px;"><img class="logo" src="{{ $logo }}" alt="Logo"></td>
                    @endif
                    <td>
                        <div class="neg-nombre">{{ $negocio }}</div>
                        @if ($empresa?->razon_social && $empresa->razon_social !== $negocio)
                            <div class="neg-meta">{{ $empresa->razon_social }}</div>
                        @endif
                        @if ($empresa?->ruc)<div class="neg-meta">RUC {{ $empresa->ruc }}</div>@endif
                        @if ($direccion)<div class="neg-meta">{{ $direccion }}</div>@endif
                        @if ($telefono)<div class="neg-meta">Tel. {{ $telefono }}</div>@endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="r" style="width: 220px;">
            <div class="doc-box">
                <div class="tit">{{ $docTipo }}</div>
                <div class="num">{{ $docNumero }}</div>
                @if ($cpe || ($venta->numero_comprobante && $venta->numero_comprobante !== $venta->numero))
                    <div class="ref">Ref. interna: {{ $venta->numero }} · ID {{ $venta->id }}</div>
                @else
                    <div class="ref">ID interno: {{ $venta->id }}</div>
                @endif
                @if ($anulada)<div class="badge-anulada">VENTA ANULADA</div>@endif
            </div>
        </td>
    </tr>
</table>

{{-- ═══ Cliente + datos de la venta ═══ --}}
<table class="info-wrap">
    <tr>
        <td style="width: 50%; padding-right: 6px;">
            <div class="box">
                <div class="label">Cliente</div>
                <table class="kv">
                    <tr><td class="k">Nombre</td><td class="b">{{ $cliNombre }}</td></tr>
                    @if ($cliDoc)<tr><td class="k">Documento</td><td>{{ $cliDoc }}</td></tr>@endif
                    @if ($cliente?->direccion)<tr><td class="k">Dirección</td><td>{{ $cliente->direccion }}</td></tr>@endif
                    @if ($cliente?->telefono)<tr><td class="k">Teléfono</td><td>{{ $cliente->telefono }}</td></tr>@endif
                    @if ($cliente?->email)<tr><td class="k">Correo</td><td>{{ $cliente->email }}</td></tr>@endif
                </table>
            </div>
        </td>
        <td style="width: 50%; padding-left: 6px;">
            <div class="box">
                <div class="label">Datos de la venta</div>
                <table class="kv">
                    <tr><td class="k">Fecha</td><td class="b">{{ $fecha($venta->fecha_venta, 'd/m/Y h:i A') }}</td></tr>
                    <tr><td class="k">Vendedor</td><td>{{ $venta->user?->name ?? '—' }}</td></tr>
                    @if ($caja)<tr><td class="k">Caja</td><td>{{ $caja->nombre }}</td></tr>@endif
                    @if ($venta->local)<tr><td class="k">Local</td><td>{{ $venta->local->nombre }}</td></tr>@endif
                    @if ($moneda === 'USD')
                        <tr><td class="k">Moneda</td><td>Dólares (TC {{ number_format((float) $venta->tipo_cambio, 3) }})</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ═══ Ítems ═══ --}}
<div class="sec-title">Detalle de productos ({{ $items->count() }})</div>
<table class="items">
    <thead>
        <tr>
            <th class="c" style="width: 22px;">#</th>
            <th>Producto</th>
            <th style="width: 82px;">Presentación</th>
            <th class="c" style="width: 46px;">Cant.</th>
            <th class="r" style="width: 66px;">P. Unit.</th>
            <th class="r" style="width: 62px;">Desc.</th>
            <th class="r" style="width: 76px;">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $i => $it)
            <tr>
                <td class="c muted">{{ $i + 1 }}</td>
                <td class="b">{{ $it->producto_nombre }}</td>
                <td class="muted">{{ $it->unidad_nombre ?: '—' }}</td>
                <td class="c">{{ $cant($it->cantidad) }}</td>
                <td class="r">
                    {{ $money($it->precio_unitario) }}
                    @if ((float) $it->precio_original > 0 && (float) $it->precio_original != (float) $it->precio_unitario)
                        <div class="tachado">{{ $money($it->precio_original) }}</div>
                    @endif
                </td>
                <td class="r">
                    @if ((float) $it->descuento_item > 0)
                        <span class="desc-rojo">-{{ $money($it->descuento_item) }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td class="r b">{{ $money($it->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══ Totales + importe en letras ═══ --}}
<table class="tots-wrap">
    <tr>
        <td style="padding-right: 14px;">
            <div class="letras">{{ $enLetras }}</div>
        </td>
        <td style="width: 240px;">
            <table class="tots">
                <tr><td class="k">Subtotal</td><td class="v">{{ $money($venta->subtotal) }}</td></tr>
                @if ((float) $venta->descuento_total > 0)
                    <tr><td class="k">Descuento</td><td class="v desc-rojo">-{{ $money($venta->descuento_total) }}</td></tr>
                @endif
                <tr><td class="k">IGV (18%) incluido</td><td class="v">{{ $money($venta->igv) }}</td></tr>
                <tr class="grand"><td>TOTAL</td><td class="r">{{ $money($venta->total) }}</td></tr>
                @if ($moneda === 'USD' && (float) $venta->monto_moneda > 0)
                    <tr><td class="k">Equivalente</td><td class="v">{{ $money($venta->monto_moneda, 'US$') }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- ═══ Pagos ═══ --}}
@if ($pagos->isNotEmpty())
    <div class="sec-title">Pagos</div>
    <table class="plain">
        <thead>
            <tr>
                <th>Método</th>
                <th>Cuenta</th>
                <th>Referencia</th>
                <th class="r" style="width: 80px;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pagos as $p)
                <tr>
                    <td class="b">{{ $p->metodoPago?->nombre ?? '—' }}</td>
                    <td class="muted">
                        {{ $p->cuentaMetodoPago?->nombre ?? '—' }}
                        @if ($p->cuentaMetodoPago?->banco) ({{ $p->cuentaMetodoPago->banco }}) @endif
                    </td>
                    <td class="muted">{{ $p->referencia ?: '—' }}</td>
                    <td class="r b">
                        {{ $money($p->monto) }}
                        @if (strtoupper($p->moneda ?? 'PEN') === 'USD' && (float) $p->monto_moneda > 0)
                            <div class="small muted">{{ $money($p->monto_moneda, 'US$') }} · TC {{ number_format((float) $p->tipo_cambio, 3) }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($vuelto > 0)
                <tr>
                    <td colspan="3" class="r muted">Vuelto entregado</td>
                    <td class="r" style="color:#d97706; font-weight:bold;">{{ $money($vuelto) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endif

{{-- ═══ Venta al crédito + abonos ═══ --}}
@if ($venta->es_credito)
    <div class="credito">
        <span class="b">VENTA AL CRÉDITO</span>
        &nbsp;·&nbsp; Pagado: <span class="b">{{ $money($venta->monto_pagado) }}</span>
        &nbsp;·&nbsp; Saldo pendiente: <span class="b" style="color:#b45309;">{{ $money($venta->saldo_pendiente) }}</span>
        @if ($venta->fecha_vencimiento)
            &nbsp;·&nbsp; Vence: <span class="b">{{ $fecha($venta->fecha_vencimiento) }}</span>
        @endif
    </div>
    @if ($abonos->isNotEmpty())
        <div class="sec-title">Abonos recibidos</div>
        <table class="plain">
            <thead>
                <tr>
                    <th style="width: 76px;">Fecha</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th class="r" style="width: 80px;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($abonos as $ab)
                    <tr>
                        <td>{{ $fecha($ab->fecha) }}</td>
                        <td>{{ $ab->metodoPago?->nombre ?? '—' }}</td>
                        <td class="muted">{{ $ab->referencia ?: '—' }}</td>
                        <td class="r b">{{ $money($ab->monto) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

{{-- ═══ Pendiente por entregar ═══ --}}
@if ($pendientes->isNotEmpty())
    <div class="sec-title">Mercadería pendiente por entregar</div>
    <table class="plain">
        <thead>
            <tr>
                <th>Producto</th>
                <th style="width: 82px;">Presentación</th>
                <th class="c" style="width: 60px;">Comprado</th>
                <th class="c" style="width: 60px;">Entregado</th>
                <th class="c" style="width: 60px;">Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pendientes as $ai)
                <tr>
                    <td class="b">{{ $ai->producto_nombre }}</td>
                    <td class="muted">{{ $ai->unidad_nombre ?: '—' }}</td>
                    <td class="c">{{ $cant($ai->cantidad) }}</td>
                    <td class="c">{{ $cant((float) $ai->cantidad - (float) $ai->cantidad_pendiente) }}</td>
                    <td class="c b" style="color:#b45309;">{{ $cant($ai->cantidad_pendiente) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small muted" style="margin-top: 3px;">
        La mercadería pendiente ya está pagada y se entrega contra este documento.
    </div>
@endif

{{-- ═══ Descuentos autorizados ═══ --}}
@if ($descLogs->isNotEmpty())
    <div class="sec-title">Descuentos aplicados</div>
    <table class="plain">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Autorizado por</th>
                <th class="r" style="width: 80px;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($descLogs as $log)
                <tr>
                    <td>{{ $log->concepto?->nombre ?? '—' }}</td>
                    <td class="muted">{{ $log->user?->name ?? '—' }}</td>
                    <td class="r desc-rojo b">-{{ $money($log->monto_descuento) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ═══ Observación ═══ --}}
@if ($venta->observacion)
    <div class="obs">
        <span class="b muted small" style="text-transform: uppercase; letter-spacing: .8px;">Observación</span><br>
        {{ $venta->observacion }}
    </div>
@endif

{{-- ═══ Comprobante electrónico ═══ --}}
@if ($cpe)
    <div class="cpe">
        <span class="b">Comprobante electrónico:</span> {{ $docTipo }} {{ $cpe->numero }}.
        Representación impresa del comprobante electrónico. Consulte en www.sunat.gob.pe
        @if (trim((string) ($cpe->hash_cpe ?? '')) !== '')
            <br>Hash: {{ $cpe->hash_cpe }}
        @endif
    </div>
@endif

<div class="footer">
    {{ $negocio }} · Venta {{ $venta->numero }} · Documento generado el {{ now()->format('d/m/Y h:i A') }}
</div>

</body>
</html>
