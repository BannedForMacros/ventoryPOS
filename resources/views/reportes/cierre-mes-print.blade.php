<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cierre del período · {{ $empresa->razon_social ?? $empresa->nombre_comercial ?? 'Empresa' }}</title>
<style>
    :root {
        --navy:   #0F4C81;
        --mint:   #00A876;
        --mint-bg:#E7F6F0;
        --coral:  #D94B2B;
        --coral-bg:#FBEEEA;
        --amber-bg:#FCF4E0;
        --ink:    #14273B;
        --slate:  #5C6B7C;
        --faint:  #97A3B0;
        --line:   #E7EBF0;
        --cloud:  #F5F7FA;
        --white:  #FFFFFF;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 11.5px; line-height: 1.5; color: var(--ink);
        background: #E9EDF2;
    }

    /* ── Herramienta (nunca sale impresa) ───────────────────────────── */
    .toolbar {
        position: sticky; top: 0; z-index: 50;
        display: flex; align-items: center; gap: 14px;
        padding: 11px 22px; margin-bottom: 16px;
        background: linear-gradient(90deg, #0F4C81, #155fa3); color: #fff;
    }
    .toolbar .marca { font-weight: 800; font-size: 14px; }
    .toolbar .marca span { color: #7fd8bb; }
    .toolbar .hint { font-size: 11px; opacity: .82; }
    .toolbar .spacer { flex: 1; }
    .toolbar .volver { color: #fff; font-size: 11.5px; text-decoration: none; opacity: .85; margin-right: 4px; }
    .toolbar .volver:hover { opacity: 1; text-decoration: underline; }
    .btn-imprimir {
        border: none; cursor: pointer; font-family: inherit;
        background: #00C48C; color: #04301f; font-weight: 800; font-size: 13px;
        padding: 9px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.28);
    }
    .btn-imprimir:hover { filter: brightness(1.07); }

    /* ── Hoja ───────────────────────────────────────────────────────── */
    .hoja { width: 210mm; margin: 0 auto; background: var(--white); padding: 14mm 14mm 8mm; }
    @media print {
        body { background: none; }
        .toolbar { display: none !important; }
        .hoja { width: 100%; margin: 0; padding: 0; }
        @page { size: A4; margin: 13mm 13mm 15mm; }
        .seccion, .tarjeta, .rompe { break-inside: avoid; }
        tr, .py .fila { break-inside: avoid; }
        thead { display: table-header-group; }
    }

    /* ── Membrete ───────────────────────────────────────────────────── */
    .membrete { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px;
        padding-bottom: 14px; border-bottom: 3px solid var(--navy); margin-bottom: 18px; }
    .membrete .org .nombre { font-size: 18px; font-weight: 800; color: var(--navy); }
    .membrete .org .detalle { font-size: 10.5px; color: var(--slate); margin-top: 3px; }
    .membrete .periodo { text-align: right; background: var(--mint-bg); border: 1px solid #c3e9db;
        border-radius: 12px; padding: 8px 18px; }
    .membrete .periodo .lbl { font-size: 8.5px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #0e7c59; }
    .membrete .periodo .rango { font-size: 16px; font-weight: 800; color: var(--ink); letter-spacing: .3px; }
    .membrete .periodo .gen { font-size: 9px; color: var(--slate); }

    /* ── HÉROE: la utilidad ─────────────────────────────────────────── */
    .hero { border-radius: 16px; padding: 20px 22px; margin-bottom: 22px;
        background: linear-gradient(135deg, #E7F6F0 0%, #FFFFFF 65%);
        border: 1.5px solid #bfe9db; }
    .hero.negativa { background: linear-gradient(135deg, #FBEEEA 0%, #FFFFFF 65%); border-color: #f0c5b8; }
    .hero .grid { display: flex; align-items: center; flex-wrap: wrap; gap: 16px 34px; }
    .hero .principal { flex: 1 1 240px; min-width: 240px; }
    .hero .etiqueta { font-size: 9px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: var(--slate); }
    .hero .numero { font-size: 40px; font-weight: 800; letter-spacing: -.6px; line-height: 1; color: #008a5f; margin: 4px 0 6px; }
    .hero.negativa .numero { color: #cf4a20; }
    .hero .sub { font-size: 10.5px; color: var(--slate); }
    .hero .sub b { color: var(--ink); }
    .hero .desglose { display: flex; flex-wrap: wrap; gap: 10px; }
    .hero .dato { background: rgba(255,255,255,.9); border: 1px solid var(--line); border-radius: 12px;
        padding: 9px 14px; text-align: center; min-width: 108px; }
    .hero .dato .lbl { font-size: 7.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--faint); }
    .hero .dato .val { font-size: 14.5px; font-weight: 800; color: var(--ink); margin-top: 2px; white-space: nowrap;
        font-variant-numeric: tabular-nums; }
    .hero .dato .val.menos { color: var(--slate); }
    .hero .dato .val.rojo { color: var(--coral); }
    .hero .dato .val.verde { color: #008a5f; }

    /* ── Secciones ──────────────────────────────────────────────────── */
    .seccion { display: flex; align-items: center; gap: 9px; margin: 24px 0 10px; }
    .seccion .titulo { font-size: 13px; font-weight: 800; color: var(--navy); letter-spacing: .2px; }
    .seccion .barra { width: 5px; height: 16px; border-radius: 3px; background: var(--navy); }
    .seccion .chip { margin-left: auto; font-size: 9.5px; font-weight: 700; color: var(--slate);
        background: var(--cloud); border: 1px solid var(--line); padding: 3px 11px; border-radius: 999px; }
    .seccion.primer { margin-top: 0; }

    /* ── Estado de resultados ───────────────────────────────────────── */
    .py { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
    .py .fila { display: flex; justify-content: space-between; align-items: baseline; gap: 20px;
        padding: 9px 20px; border-bottom: 1px solid var(--line); }
    .py .fila:last-child { border-bottom: none; }
    .py .nom { font-size: 12px; font-weight: 600; }
    .py .nom .nota { display: block; font-weight: 400; font-size: 9.5px; color: var(--faint); margin-top: 1px; }
    .py .monto { font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
    .py .fila.final { background: linear-gradient(90deg, #E7F6F0, #F6FCF9); border-top: 2px solid #b9e7d6; }
    .py .fila.final .nom { font-size: 13.5px; font-weight: 800; }
    .py .fila.final .monto { font-size: 19px; font-weight: 800; }

    /* ── Tarjetas de tabla ──────────────────────────────────────────── */
    .tarjeta { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    .tabla th { text-align: left; font-size: 8.5px; font-weight: 800; letter-spacing: 1.1px; text-transform: uppercase;
        color: #4a6480; background: var(--cloud); border-bottom: 1.5px solid #dde4ec; padding: 9px 20px; }
    .tabla th.r { text-align: right; }
    .tabla td { padding: 10px 20px; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .tabla tbody tr:last-child td { border-bottom: none; }
    .tabla .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tabla td.num { font-size: 12.5px; font-weight: 700; }
    .tabla .neg { color: var(--coral); }
    .tabla .pos { color: #008a5f; }
    .tabla .muted { color: var(--slate); }
    .tabla td .sub { display: block; font-size: 9px; font-weight: 500; color: var(--faint); }
    .tabla tfoot td { background: #F1F6FB; font-weight: 800; font-size: 12.5px; padding: 10px 20px; border-top: 2px solid #c9d6e4; }
    .tabla tfoot td.num { font-size: 13px; color: var(--navy); }

    /* ── Resumen: bloques grandes ───────────────────────────────────── */
    .resumen { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .bloque { border: 1px solid var(--line); border-left: 4px solid var(--navy); border-radius: 12px;
        background: var(--cloud); padding: 12px 16px; }
    .bloque .lbl { font-size: 8px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: var(--slate); }
    .bloque .val { font-size: 19px; font-weight: 800; color: var(--navy); margin: 3px 0 1px;
        font-variant-numeric: tabular-nums; }
    .bloque .nota { font-size: 9px; color: var(--faint); }
    .bloque.mint { border-left-color: var(--mint); } .bloque.mint .val { color: #008a5f; }
    .bloque.coral { border-left-color: var(--coral); } .bloque.coral .val { color: var(--coral); }

    /* estados SUNAT: píldoras anchas */
    .sunat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .sunat { border: 1px solid var(--line); border-radius: 12px; padding: 10px 14px; display: flex;
        align-items: center; justify-content: space-between; gap: 10px; background: var(--cloud); }
    .sunat .st { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 10.5px; }
    .sunat .st .bolita { width: 8px; height: 8px; border-radius: 50%; flex: none; }
    .sunat .n { font-size: 15px; font-weight: 800; color: var(--navy); font-variant-numeric: tabular-nums; }

    .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid var(--line);
        display: flex; justify-content: space-between; gap: 12px; font-size: 8.5px; color: var(--faint); }
</style>
</head>
<body>

@php
    $razonSocial = $empresa->razon_social ?? ($empresa->nombre_comercial ?? 'Empresa');
    $fmt   = fn ($n) => 'S/ ' . number_format((float) $n, 2, '.', ',');
    $nFmt  = fn ($n) => number_format((float) $n, 0, '.', ',');
    $fmtFecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
    $comprobanteLabel = [
        'ticket' => 'Ticket', 'boleta' => 'Boleta', 'factura' => 'Factura',
        'boleta_externa' => 'Boleta externa', 'factura_externa' => 'Factura externa',
    ];
    $estadoSunat = [
        'aceptado' => ['Aceptados por SUNAT', '#00A876'],
        'enviando' => ['Enviando', '#1A73C8'],
        'enviado' => ['Enviados (por confirmar)', '#1A73C8'],
        'pendiente_resumen' => ['Pendientes de resumen diario', '#E0A500'],
        'en_resumen' => ['En resumen diario', '#E0A500'],
        'pendiente' => ['Pendientes de envío', '#E0A500'],
        'error_envio' => ['Error de envío', '#D94B2B'],
        'error_mapeo' => ['Error de datos', '#D94B2B'],
        'rechazado' => ['Rechazados por SUNAT', '#D94B2B'],
        'anulado' => ['Anulados (nota de crédito)', '#5C6B7C'],
        'no_emitido' => ['No emitidos', '#97A3B0'],
        'simulado' => ['Simulados (prueba)', '#97A3B0'],
    ];
    $cobradoTotal = collect($por_metodo)->sum('total');
    $neta = $kpis['utilidad_neta'];
    $monedaNota = $local ? " · Local: {$local}" : '';
@endphp

{{-- Barra de herramienta: nunca sale en el PDF --}}
<div class="toolbar">
    <span class="marca">Ventory<span>POS</span></span>
    <span class="hint">Vista de impresión · {!! $razonSocial !!}</span>
    <span class="spacer"></span>
    <a class="volver" href="javascript:window.close()">← Volver</a>
    <button class="btn-imprimir" onclick="window.print()">Guardar como PDF</button>
</div>

<div class="hoja">

    {{-- Membrete --}}
    <div class="membrete">
        <div class="org">
            <div class="nombre">{{ $razonSocial }}</div>
            <div class="detalle">@if($empresa->ruc) RUC {{ $empresa->ruc }} &nbsp;·&nbsp; @endif Reporte de cierre de período{{ $monedaNota }}</div>
        </div>
        <div class="periodo">
            <div class="lbl">Cierre del período</div>
            <div class="rango">{{ $fmtFecha($filters['fecha_desde']) }} — {{ $fmtFecha($filters['fecha_hasta']) }}</div>
            <div class="gen">Generado el {{ $generado }}</div>
        </div>
    </div>

    {{-- HÉROE: la utilidad neta --}}
    <div class="hero {{ $neta < 0 ? 'negativa' : '' }}">
        <div class="grid">
            <div class="principal">
                <div class="etiqueta">Utilidad neta del período</div>
                <div class="numero">{{ $fmt($neta) }}</div>
                <div class="sub">
                    Margen neto: <b>{{ $kpis['margen_neto'] ?? 0 }}%</b>
                    @if($kpis['variacion_ventas'] !== null)
                        &nbsp;·&nbsp; Ventas <b>{{ $kpis['variacion_ventas'] >= 0 ? '+' : '' }}{{ $kpis['variacion_ventas'] }}%</b> vs período anterior
                    @endif
                </div>
            </div>
            <div class="desglose">
                <div class="dato"><div class="lbl">Ventas</div><div class="val verde">{{ $fmt($kpis['ventas']) }}</div></div>
                <div class="dato"><div class="lbl">Costo</div><div class="val menos">− {{ $fmt($kpis['costo']) }}</div></div>
                <div class="dato"><div class="lbl">Gastos</div><div class="val rojo">− {{ $fmt($kpis['gastos']) }}</div></div>
                <div class="dato"><div class="lbl">Devoluciones</div><div class="val rojo">− {{ $fmt($kpis['devoluciones']) }}</div></div>
            </div>
        </div>
    </div>

    {{-- Estado de resultados --}}
    <div class="seccion primer"><span class="barra"></span><span class="titulo">Estado de resultados</span>
        <span class="chip">{{ $nFmt($kpis['ventas_count']) }} ventas · {{ $kpis['gastos_count'] }} gastos</span></div>
    <div class="py">
        <div class="fila">
            <span class="nom">Ventas <span class="nota">{{ $nFmt($kpis['ventas_count']) }} comprobantes · IGV {{ $fmt($kpis['igv']) }}</span></span>
            <span class="monto pos">{{ $fmt($kpis['ventas']) }}</span>
        </div>
        <div class="fila">
            <span class="nom">Costo de lo vendido <span class="nota">Costo congelado al momento de cada venta</span></span>
            <span class="monto neg">− {{ $fmt($kpis['costo']) }}</span>
        </div>
        <div class="fila">
            <span class="nom">Utilidad bruta <span class="nota">Margen bruto {{ $kpis['margen_bruto'] ?? 0 }}%</span></span>
            <span class="monto">{{ $fmt($kpis['utilidad_bruta']) }}</span>
        </div>
        <div class="fila">
            <span class="nom">Gastos operativos <span class="nota">Detalle por tipo más abajo</span></span>
            <span class="monto neg">− {{ $fmt($kpis['gastos']) }}</span>
        </div>
        <div class="fila">
            <span class="nom">Devoluciones <span class="nota">{{ $kpis['devoluciones_count'] }} devoluciones en el período</span></span>
            <span class="monto neg">− {{ $fmt($kpis['devoluciones']) }}</span>
        </div>
        <div class="fila final">
            <span class="nom">Utilidad neta del período <span class="nota">Margen neto {{ $kpis['margen_neto'] ?? 0 }}%</span></span>
            <span class="monto {{ $neta >= 0 ? 'pos' : 'neg' }}">{{ $fmt($neta) }}</span>
        </div>
    </div>

    {{-- Resumen del cierre --}}
    <div class="seccion"><span class="barra"></span><span class="titulo">Resumen del cierre</span></div>
    <div class="resumen rompe">
        <div class="bloque mint"><div class="lbl">Cobrado en el período</div><div class="val">{{ $fmt($cobradoTotal) }}</div>
            <div class="nota">Ventas de contado + abonos a crédito</div></div>
        <div class="bloque"><div class="lbl">Por cobrar (créditos)</div><div class="val">{{ $fmt($kpis['por_cobrar']) }}</div>
            <div class="nota">{{ $nFmt($kpis['por_cobrar_count']) }} ventas con saldo al corte</div></div>
        <div class="bloque"><div class="lbl">Compras del período</div><div class="val">{{ $fmt($kpis['compras']) }}</div>
            <div class="nota">Por pagar {{ $fmt($kpis['compras_pendiente']) }}</div></div>
        <div class="bloque coral"><div class="lbl">Ventas anuladas</div><div class="val">{{ $nFmt($kpis['anuladas_count']) }}</div>
            <div class="nota">{{ $fmt($kpis['anuladas_monto']) }} revertidos</div></div>
    </div>

    {{-- Métodos de pago --}}
    <div class="seccion"><span class="barra"></span><span class="titulo">Cobrado por método de pago</span>
        <span class="chip">Total {{ $fmt($cobradoTotal) }}</span></div>
    @if(count($por_metodo) === 0)
        <div class="tarjeta" style="padding:16px 20px;color:var(--faint)">Sin cobros en el período</div>
    @else
    <div class="tarjeta rompe">
        <table class="tabla">
            <thead><tr><th>Método</th><th class="r">Ventas</th><th class="r">Abonos de crédito</th><th class="r">Total cobrado</th></tr></thead>
            <tbody>
            @foreach($por_metodo as $m)
            <tr>
                <td style="font-weight:700">{{ $m['nombre'] }}</td>
                <td class="num">{{ $fmt($m['ventas']) }}</td>
                <td class="num muted">{{ $m['abonos'] > 0 ? $fmt($m['abonos']) : '—' }}</td>
                <td class="num" style="color:#0F4C81">{{ $fmt($m['total']) }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Total cobrado</td><td class="num"></td><td class="num"></td><td class="num">{{ $fmt($cobradoTotal) }}</td></tr></tfoot>
        </table>
    </div>
    @endif

    {{-- Comprobantes --}}
    <div class="seccion"><span class="barra"></span><span class="titulo">Comprobantes del período</span>
        <span class="chip">{{ $nFmt(collect($por_comprobante)->sum('emitidos')) }} emitidos</span></div>
    @if(count($por_comprobante) === 0)
        <div class="tarjeta" style="padding:16px 20px;color:var(--faint)">Sin comprobantes en el período</div>
    @else
    <div class="tarjeta rompe">
        <table class="tabla">
            <thead><tr><th>Tipo</th><th class="r">Emitidos</th><th class="r">Anulados</th><th>Numeración</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach($por_comprobante as $c)
            <tr>
                <td style="font-weight:700">{{ $comprobanteLabel[$c['tipo']] ?? $c['tipo'] }}</td>
                <td class="num">{{ $nFmt($c['emitidos']) }}</td>
                <td class="num {{ $c['anulados'] > 0 ? 'neg' : 'muted' }}">{{ $c['anulados'] > 0 ? $nFmt($c['anulados']) : '—' }}</td>
                <td class="muted" style="font-size:10px">{{ $c['primer_numero']
                    ? ($c['primer_numero'] === $c['ultimo_numero'] ? $c['primer_numero'] : $c['primer_numero'] . ' → ' . $c['ultimo_numero'])
                    : '—' }}</td>
                <td class="num">{{ $fmt($c['total']) }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Total comprobantes</td><td class="num"></td><td class="num"></td><td></td><td class="num">{{ $fmt(collect($por_comprobante)->sum('total')) }}</td></tr></tfoot>
        </table>
    </div>
    @endif

    {{-- Electrónicos SUNAT --}}
    @if(count($electronicos) > 0)
    <div class="seccion"><span class="barra"></span><span class="titulo">Comprobantes electrónicos SUNAT</span></div>
    <div class="sunat-grid rompe">
        @foreach($electronicos as $e)
            @php $s = $estadoSunat[$e['estado']] ?? [$e['estado'], '#97A3B0']; @endphp
            <div class="sunat"><span class="st"><span class="bolita" style="background:{{ $s[1] }}"></span>{{ $s[0] }}</span>
                <span class="n">{{ $nFmt($e['count']) }}</span></div>
        @endforeach
    </div>
    @endif

    {{-- Créditos --}}
    <div class="seccion"><span class="barra"></span><span class="titulo">Créditos · cuentas por cobrar</span>
        <span class="chip">Saldo al corte {{ $fmt($kpis['por_cobrar']) }}</span></div>
    <div class="resumen rompe" style="grid-template-columns:repeat(3,1fr);margin-bottom:12px">
        <div class="bloque"><div class="lbl">Crédito otorgado</div><div class="val">{{ $fmt($kpis['credito_otorgado']) }}</div>
            <div class="nota">{{ $nFmt($kpis['credito_count']) }} ventas a crédito</div></div>
        <div class="bloque mint"><div class="lbl">Cobrado en abonos</div><div class="val">{{ $fmt($kpis['credito_cobrado']) }}</div>
            <div class="nota">Durante el período</div></div>
        <div class="bloque coral"><div class="lbl">Por cobrar al corte</div><div class="val">{{ $fmt($kpis['por_cobrar']) }}</div>
            <div class="nota">{{ $nFmt($kpis['por_cobrar_count']) }} clientes con saldo</div></div>
    </div>
    @if(count($top_deudores) > 0)
    <div class="tarjeta rompe">
        <table class="tabla">
            <thead><tr><th>Mayores deudores al corte</th><th class="r">Ventas</th><th class="r">Saldo pendiente</th></tr></thead>
            <tbody>
            @foreach($top_deudores as $d)
            <tr><td style="font-weight:600">{{ $d['nombre'] }}</td><td class="num">{{ $nFmt($d['ventas']) }}</td>
                <td class="num" style="color:#0F4C81">{{ $fmt($d['saldo']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Gastos --}}
    <div class="seccion"><span class="barra"></span><span class="titulo">Gastos del período</span>
        <span class="chip">Total {{ $fmt($kpis['gastos']) }}</span></div>
    @if(count($gastos_por_tipo) === 0)
        <div class="tarjeta" style="padding:16px 20px;color:var(--faint)">Sin gastos en el período</div>
    @else
    <div class="tarjeta rompe">
        <table class="tabla">
            <thead><tr><th>Tipo de gasto</th><th class="r">Registros</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach($gastos_por_tipo as $g)
            <tr><td style="font-weight:600">{{ $g['nombre'] }}</td>
                <td class="num muted">{{ $nFmt($g['count']) }}</td>
                <td class="num">{{ $fmt($g['total']) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Total gastos</td><td></td><td class="num">{{ $fmt($kpis['gastos']) }}</td></tr></tfoot>
        </table>
    </div>
    @endif

    {{-- Compras --}}
    @if(count($compras_por_proveedor) > 0)
    <div class="seccion"><span class="barra"></span><span class="titulo">Compras por proveedor</span>
        <span class="chip">Por pagar {{ $fmt($kpis['compras_pendiente']) }}</span></div>
    <div class="tarjeta rompe">
        <table class="tabla">
            <thead><tr><th>Proveedor</th><th class="r">Compras</th><th class="r">Total</th><th class="r">Pagado</th><th class="r">Pendiente</th></tr></thead>
            <tbody>
            @foreach($compras_por_proveedor as $p)
            <tr>
                <td style="font-weight:600">{{ $p['nombre'] }}</td>
                <td class="num muted">{{ $nFmt($p['count']) }}</td>
                <td class="num">{{ $fmt($p['total']) }}</td>
                <td class="num muted">{{ $fmt($p['pagado']) }}</td>
                <td class="num {{ $p['pendiente'] > 0 ? 'neg' : 'muted' }}">{{ $p['pendiente'] > 0 ? $fmt($p['pendiente']) : '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="foot">
        <span>{{ $razonSocial }} · Reporte de cierre del período · {{ $filters['fecha_desde'] }} al {{ $filters['fecha_hasta'] }}</span>
        <span>Generado por VentoryPOS el {{ $generado }}</span>
    </div>

</div>

<script>
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>
