<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cierre del período · {{ $empresa->razon_social ?? $empresa->nombre_comercial ?? 'Empresa' }}</title>
<style>
    :root {
        --navy:   #0F4C81;
        --sky:    #1A73C8;
        --mint:   #00A876;
        --mint-bg:#E8F7F1;
        --coral:  #E2572C;
        --coral-bg:#FBEDE7;
        --amber:  #B97B00;
        --amber-bg:#FBF3E0;
        --ink:    #12263A;
        --slate:  #5B6B7C;
        --faint:  #9AA7B4;
        --line:   #E5EAF0;
        --cloud:  #F6F8FA;
        --white:  #FFFFFF;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 11px; line-height: 1.45; color: var(--ink);
        background: #EDF0F4;
    }

    /* ── Herramienta (nunca sale impresa) ───────────────────────────── */
    .toolbar {
        position: sticky; top: 0; z-index: 50;
        display: flex; align-items: center; gap: 12px;
        padding: 10px 20px; margin-bottom: 18px;
        background: linear-gradient(90deg, #0F4C81, #145e9e);
        color: #fff;
    }
    .toolbar .marca { font-weight: 800; font-size: 13px; letter-spacing: .3px; }
    .toolbar .marca span { color: #7fd8bb; }
    .toolbar .spacer { flex: 1; }
    .toolbar .hint { font-size: 11px; opacity: .8; }
    .btn-imprimir {
        border: none; cursor: pointer; font-family: inherit;
        background: var(--mint, #00C48C); color: #04251b; font-weight: 800;
        font-size: 12.5px; padding: 8px 18px; border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,.25); transition: filter .15s;
    }
    .btn-imprimir:hover { filter: brightness(1.07); }
    .toolbar .volver { color: #fff; font-size: 11.5px; text-decoration: none; opacity: .85; }
    .toolbar .volver:hover { opacity: 1; text-decoration: underline; }

    /* ── Hoja ───────────────────────────────────────────────────────── */
    .hoja {
        width: 210mm; min-height: 297mm;
        margin: 0 auto; background: var(--white);
        padding: 12mm 13mm 10mm;
    }
    @media print {
        body { background: none; }
        .toolbar { display: none !important; }
        .hoja { width: 100%; margin: 0; padding: 0; }
        @page { size: A4; margin: 12mm 12mm 14mm; }
        .seccion, .card, .evita-corte { break-inside: avoid; }
        tr { break-inside: avoid; }
        thead { display: table-header-group; }
    }

    /* ── Encabezado del documento ────────────────────────────────────── */
    .membrete { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px;
        padding-bottom: 12px; border-bottom: 2.5px solid var(--navy); margin-bottom: 14px; }
    .membrete .org { min-width: 0; }
    .membrete .nombre { font-size: 17px; font-weight: 800; color: var(--navy); letter-spacing: .2px; }
    .membrete .detalle { font-size: 10.5px; color: var(--slate); margin-top: 2px; }
    .membrete .doc-titulo {
        text-align: right; padding: 6px 14px; border-radius: 10px;
        background: var(--mint-bg); border: 1px solid #bfe9d9;
    }
    .membrete .doc-titulo .t1 { font-size: 8.5px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #0f7a5a; }
    .membrete .doc-titulo .t2 { font-size: 14px; font-weight: 800; color: var(--ink); }
    .membrete .doc-titulo .t3 { font-size: 9px; color: var(--slate); }

    /* ── HÉROE: la utilidad ──────────────────────────────────────────── */
    .hero { border-radius: 14px; padding: 16px 18px; margin-bottom: 14px;
        background: linear-gradient(135deg, #EAF6F1 0%, #FFFFFF 62%);
        border: 1.5px solid #bfe9d9;
        box-shadow: inset 0 0 0 1px rgba(0,168,118,.04);
    }
    .hero.negativa { background: linear-gradient(135deg, #FBEDE7 0%, #FFFFFF 62%); border-color: #f2c9bb; }
    .hero .grid { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
    .hero .principal { min-width: 190px; }
    .hero .etiqueta { font-size: 9px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; color: var(--slate); }
    .hero .numero { font-size: 34px; font-weight: 800; letter-spacing: -.5px; line-height: 1.05; color: #008a5f; }
    .hero.negativa .numero { color: #cf4a20; }
    .hero .sub { font-size: 10px; color: var(--slate); margin-top: 3px; }
    .hero .desglose { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
    .hero .dato { background: rgba(255,255,255,.75); border: 1px solid var(--line); border-radius: 10px; padding: 6px 10px; min-width: 96px; }
    .hero .dato .lbl { font-size: 7.5px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--faint); }
    .hero .dato .val { font-size: 12.5px; font-weight: 700; color: var(--ink); white-space: nowrap; }
    .hero .dato .val.menos { color: var(--slate); }
    .hero .dato .val.rojo { color: var(--coral); }
    .hero .dato .val.verde { color: #008a5f; }

    /* ── Secciones y tarjetas ────────────────────────────────────────── */
    .seccion { font-size: 11.5px; font-weight: 800; color: var(--navy); letter-spacing: .2px;
        display: flex; align-items: center; gap: 8px; margin: 16px 0 8px; }
    .seccion::before { content: ""; width: 4px; height: 14px; border-radius: 2px; background: var(--navy); }
    .seccion .chip { margin-left: auto; font-size: 9px; font-weight: 700; color: var(--slate);
        background: var(--cloud); border: 1px solid var(--line); padding: 2px 9px; border-radius: 999px; }

    .card { border: 1px solid var(--line); border-radius: 12px; background: var(--white); overflow: hidden; }
    .card.piel { background: var(--cloud); }

    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }

    /* ── Tablas ──────────────────────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; }
    .tabla th {
        text-align: left; font-size: 8px; font-weight: 700; letter-spacing: .9px;
        text-transform: uppercase; color: var(--navy);
        padding: 7px 12px; border-bottom: 1.5px solid #d6dee8; background: #F3F7FB;
    }
    .tabla th.r, .num { text-align: right; }
    .tabla td { padding: 6.5px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .tabla tbody tr:last-child td { border-bottom: none; }
    .tabla td.num { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; }
    .tabla .neg { color: var(--coral); }
    .tabla .pos { color: #008a5f; }
    .tabla .muted { color: var(--slate); font-weight: 500; }
    .tabla .fila-sub { font-size: 10px; color: var(--slate); display: block; font-weight: 500; }
    .tabla tr.total td { background: var(--mint-bg); font-weight: 800; border-top: 1.5px solid #bfe9d9; }
    .tabla tr.total td { color: var(--ink); }
    .tabla tr.fuerte td { background: #F1F6FB; font-weight: 800; }

    /* estado de resultados */
    .py .fila { display: flex; justify-content: space-between; align-items: baseline; gap: 14px;
        padding: 7px 16px; border-bottom: 1px solid var(--line); }
    .py .fila:last-child { border-bottom: none; }
    .py .nom { font-weight: 600; }
    .py .nom .nota { display: block; font-weight: 400; font-size: 9px; color: var(--faint); }
    .py .monto { font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
    .py .fila.final { background: linear-gradient(90deg, #E8F7F1, #F4FBF8); border-top: 2px solid #bfe9d9; }
    .py .fila.final .nom { font-size: 12px; font-weight: 800; }
    .py .fila.final .monto { font-size: 16px; font-weight: 800; }

    /* chips de resumen */
    .kpi { background: var(--cloud); border: 1px solid var(--line); border-radius: 12px; padding: 9px 12px; }
    .kpi .lbl { font-size: 7.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--faint); }
    .kpi .val { font-size: 16px; font-weight: 800; color: var(--navy); margin-top: 1px; }
    .kpi .nota { font-size: 8.5px; color: var(--slate); margin-top: 1px; }

    /* estados SUNAT / chips */
    .sunat-item { display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 6px 12px; border-bottom: 1px solid var(--line); }
    .sunat-item:last-child { border-bottom: none; }
    .sunat-item .st { font-weight: 600; }
    .sunat-item .st .bolita { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 7px; }
    .sunat-item .n { font-weight: 800; color: var(--navy); font-variant-numeric: tabular-nums; }
    .vaciar { padding: 14px; color: var(--faint); font-size: 10px; text-align: center; }

    .foot { margin-top: 18px; padding-top: 8px; border-top: 1px solid var(--line);
        display: flex; justify-content: space-between; gap: 12px;
        font-size: 8.5px; color: var(--faint); }
</style>
</head>
<body>

@php
    $razonSocial = $empresa->razon_social ?? ($empresa->nombre_comercial ?? 'Empresa');
    $fmt  = fn ($n) => 'S/ ' . number_format((float) $n, 2, '.', ',');
    $nFmt = fn ($n) => number_format((float) $n, 0, '.', ',');
    $fmtFecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
    $comprobanteLabel = [
        'ticket' => 'Ticket', 'boleta' => 'Boleta', 'factura' => 'Factura',
        'boleta_externa' => 'Boleta externa', 'factura_externa' => 'Factura externa',
    ];
    $estadoSunat = [
        'aceptado' => ['Aceptados por SUNAT', 'var(--mint)', '#008a5f'],
        'enviando' => ['Enviando', 'var(--sky)', '#0F4C81'],
        'enviado' => ['Enviados (por confirmar)', 'var(--sky)', '#0F4C81'],
        'pendiente_resumen' => ['Pendientes de resumen diario', 'var(--amber)', '#B97B00'],
        'en_resumen' => ['En resumen diario', 'var(--amber)', '#B97B00'],
        'pendiente' => ['Pendientes de envío', 'var(--amber)', '#B97B00'],
        'error_envio' => ['Error de envío', 'var(--coral)', '#E2572C'],
        'error_mapeo' => ['Error de datos', 'var(--coral)', '#E2572C'],
        'rechazado' => ['Rechazados por SUNAT', 'var(--coral)', '#E2572C'],
        'anulado' => ['Anulados (nota de crédito)', 'var(--slate)', '#5B6B7C'],
        'no_emitido' => ['No emitidos', 'var(--faint)', '#9AA7B4'],
        'simulado' => ['Simulados (prueba)', 'var(--faint)', '#9AA7B4'],
    ];
    $cobradoTotal = collect($por_metodo)->sum('total');
    $neta = $kpis['utilidad_neta'];
    $monedaNota = $local ? " · Local: {$local}" : '';
@endphp

{{-- Barra de herramienta: no sale en el PDF --}}
<div class="toolbar">
    <span class="marca">Ventory<span>POS</span></span>
    <span class="hint">Vista de impresión · {!! $razonSocial !!}</span>
    <span class="spacer"></span>
    <a class="volver" href="javascript:window.close()">← Volver</a>
    <button class="btn-imprimir" onclick="window.print()">Guardar como PDF</button>
</div>

<div class="hoja">

    {{-- ── Membrete ── --}}
    <div class="membrete">
        <div class="org">
            <div class="nombre">{{ $razonSocial }}</div>
            <div class="detalle">
                @if($empresa->ruc) RUC {{ $empresa->ruc }} &nbsp;·&nbsp; @endif
                Reporte de cierre de período{{ $monedaNota }}
            </div>
        </div>
        <div class="doc-titulo">
            <div class="t1">Cierre del período</div>
            <div class="t2">{{ $fmtFecha($filters['fecha_desde']) }} — {{ $fmtFecha($filters['fecha_hasta']) }}</div>
            <div class="t3">Generado el {{ $generado }}</div>
        </div>
    </div>

    {{-- ── HÉROE: la utilidad, lo primero que se ve ── --}}
    <div class="hero {{ $neta < 0 ? 'negativa' : '' }}">
        <div class="grid">
            <div class="principal">
                <div class="etiqueta">Utilidad neta del período</div>
                <div class="numero">{{ $fmt($neta) }}</div>
                <div class="sub">
                    Margen neto: <strong>{{ $kpis['margen_neto'] ?? 0 }}%</strong>
                    @if($kpis['variacion_ventas'] !== null)
                        &nbsp;·&nbsp; Ventas {{ $kpis['variacion_ventas'] >= 0 ? '+' : '' }}{{ $kpis['variacion_ventas'] }}% vs período anterior
                    @endif
                </div>
            </div>
            <div class="desglose">
                <div class="dato"><div class="lbl">Ventas</div><div class="val verde">{{ $fmt($kpis['ventas']) }}</div></div>
                <div class="dato"><div class="lbl">Costo vendido</div><div class="val menos">− {{ $fmt($kpis['costo']) }}</div></div>
                <div class="dato"><div class="lbl">Gastos</div><div class="val rojo">− {{ $fmt($kpis['gastos']) }}</div></div>
                <div class="dato"><div class="lbl">Devoluciones</div><div class="val rojo">− {{ $fmt($kpis['devoluciones']) }}</div></div>
                <div class="dato"><div class="lbl">Utilidad bruta</div><div class="val">{{ $fmt($kpis['utilidad_bruta']) }}</div></div>
            </div>
        </div>
    </div>

    {{-- ── Estado de resultados ── --}}
    <div class="seccion">Estado de resultados <span class="chip">{{ $nFmt($kpis['ventas_count']) }} ventas · {{ $kpis['gastos_count'] }} gastos</span></div>
    <div class="card py evita-corte">
        <div class="fila">
            <span class="nom">Ventas <span class="nota">{{ $nFmt($kpis['ventas_count']) }} comprobantes · IGV {{ $fmt($kpis['igv']) }} · descuentos {{ $fmt($kpis['descuentos']) }}</span></span>
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
            <span class="nom">Gastos operativos <span class="nota">{{ $kpis['gastos_count'] }} gastos en el período</span></span>
            <span class="monto neg">− {{ $fmt($kpis['gastos']) }}</span>
        </div>
        <div class="fila">
            <span class="nom">Devoluciones <span class="nota">{{ $kpis['devoluciones_count'] }} en el período</span></span>
            <span class="monto neg">− {{ $fmt($kpis['devoluciones']) }}</span>
        </div>
        <div class="fila final">
            <span class="nom">Utilidad neta del período <span class="nota">Margen neto {{ $kpis['margen_neto'] ?? 0 }}%</span></span>
            <span class="monto {{ $neta >= 0 ? 'pos' : 'neg' }}">{{ $fmt($neta) }}</span>
        </div>
    </div>

    {{-- ── Resumen del cierre ── --}}
    <div class="seccion">Resumen del cierre</div>
    <div class="grid4 evita-corte">
        <div class="kpi"><div class="lbl">Cobrado en el período</div><div class="val">{{ $fmt($cobradoTotal) }}</div>
            <div class="nota">Ventas + abonos a crédito</div></div>
        <div class="kpi"><div class="lbl">Por cobrar (créditos)</div><div class="val">{{ $fmt($kpis['por_cobrar']) }}</div>
            <div class="nota">{{ $nFmt($kpis['por_cobrar_count']) }} ventas con saldo al corte</div></div>
        <div class="kpi"><div class="lbl">Compras del período</div><div class="val">{{ $fmt($kpis['compras']) }}</div>
            <div class="nota">Por pagar {{ $fmt($kpis['compras_pendiente']) }}</div></div>
        <div class="kpi"><div class="lbl">Ventas anuladas</div><div class="val">{{ $nFmt($kpis['anuladas_count']) }}</div>
            <div class="nota">{{ $fmt($kpis['anuladas_monto']) }} revertidos</div></div>
    </div>

    {{-- ── Comprobantes + SUNAT ── --}}
    <div class="seccion">Comprobantes del período <span class="chip">{{ $nFmt(collect($por_comprobante)->sum('emitidos')) }} emitidos</span></div>
    <div class="grid2 evita-corte">
        <div class="card">
            @if(count($por_comprobante) === 0)
                <div class="vaciar">Sin comprobantes en el período</div>
            @else
            <table class="tabla">
                <thead><tr><th>Tipo</th><th class="r">Emitidos</th><th class="r">Anulados</th><th>Numeración</th><th class="r">Total</th></tr></thead>
                <tbody>
                @foreach($por_comprobante as $c)
                <tr>
                    <td>{{ $comprobanteLabel[$c['tipo']] ?? $c['tipo'] }}</td>
                    <td class="num">{{ $nFmt($c['emitidos']) }}</td>
                    <td class="num {{ $c['anulados'] > 0 ? 'neg' : 'muted' }}">{{ $c['anulados'] > 0 ? $nFmt($c['anulados']) : '—' }}</td>
                    <td class="muted" style="font-size:9px">
                        @if($c['primer_numero'])
                            {{ $c['primer_numero'] === $c['ultimo_numero'] ? $c['primer_numero'] : $c['primer_numero'] . ' → ' . $c['ultimo_numero'] }}
                        @else — @endif
                    </td>
                    <td class="num">{{ $fmt($c['total']) }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="card">
            <div style="font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#0F7A5A;padding:8px 12px 4px">Electrónicos SUNAT</div>
            @if(count($electronicos) === 0)
                <div class="vaciar">Sin comprobantes electrónicos en el período</div>
            @else
                @foreach($electronicos as $e)
                    @php [$label, $dot, $col] = $estadoSunat[$e['estado']] ?? [$e['estado'], 'var(--faint)', '#9AA7B4']; @endphp
                    <div class="sunat-item">
                        <span class="st"><span class="bolita" style="background:{{ $dot }}"></span>{{ $label }}</span>
                        <span class="n">{{ $nFmt($e['count']) }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ── Métodos de pago + Créditos ── --}}
    <div class="seccion">Cobrado por método de pago</div>
    <div class="grid2 evita-corte">
        <div class="card">
            @if(count($por_metodo) === 0)
                <div class="vaciar">Sin cobros en el período</div>
            @else
            <table class="tabla">
                <thead><tr><th>Método</th><th class="r">Ventas</th><th class="r">Abonos</th><th class="r">Total</th></tr></thead>
                <tbody>
                @foreach($por_metodo as $m)
                <tr>
                    <td>{{ $m['nombre'] }}</td>
                    <td class="num">{{ $fmt($m['ventas']) }}</td>
                    <td class="num muted">{{ $m['abonos'] > 0 ? $fmt($m['abonos']) : '—' }}</td>
                    <td class="num">{{ $fmt($m['total']) }}</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="total"><td>Total cobrado</td><td class="num"></td><td class="num"></td><td class="num">{{ $fmt($cobradoTotal) }}</td></tr>
                </tfoot>
            </table>
            @endif
        </div>

        <div class="card" style="padding:10px 14px">
            <div style="font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#5B6B7C">Créditos · cuentas por cobrar</div>
            <div class="grid4" style="grid-template-columns:repeat(3,1fr);margin:8px 0 6px;gap:8px">
                <div class="kpi" style="padding:6px 8px"><div class="lbl">Otorgado</div><div class="val" style="font-size:12px">{{ $fmt($kpis['credito_otorgado']) }}</div></div>
                <div class="kpi" style="padding:6px 8px"><div class="lbl">Cobrado</div><div class="val" style="font-size:12px">{{ $fmt($kpis['credito_cobrado']) }}</div></div>
                <div class="kpi" style="padding:6px 8px"><div class="lbl">Por cobrar</div><div class="val" style="font-size:12px">{{ $fmt($kpis['por_cobrar']) }}</div></div>
            </div>
            @if(count($top_deudores) === 0)
                <div class="vaciar" style="padding:6px">Sin deudas al corte</div>
            @else
            <table class="tabla">
                <thead><tr><th>Mayores deudores al corte</th><th class="r">Ventas</th><th class="r">Saldo</th></tr></thead>
                <tbody>
                @foreach($top_deudores as $d)
                <tr><td>{{ $d['nombre'] }}</td><td class="num">{{ $nFmt($d['ventas']) }}</td><td class="num">{{ $fmt($d['saldo']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- ── Gastos + Compras ── --}}
    <div class="seccion">Gastos y compras</div>
    <div class="grid2 evita-corte">
        <div class="card">
            @if(count($gastos_por_tipo) === 0)
                <div class="vaciar">Sin gastos en el período</div>
            @else
            <table class="tabla">
                <thead><tr><th>Tipo de gasto</th><th class="r">Cant.</th><th class="r">Total</th></tr></thead>
                <tbody>
                @foreach($gastos_por_tipo as $g)
                <tr><td>{{ $g['nombre'] }}</td><td class="num">{{ $nFmt($g['count']) }}</td><td class="num">{{ $fmt($g['total']) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="total"><td>Total gastos</td><td></td><td class="num">{{ $fmt($kpis['gastos']) }}</td></tr>
                </tfoot>
            </table>
            @endif
        </div>

        <div class="card">
            @if(count($compras_por_proveedor) === 0)
                <div class="vaciar">Sin compras en el período</div>
            @else
            <table class="tabla">
                <thead><tr><th>Proveedor</th><th class="r">Total</th><th class="r">Pagado</th><th class="r">Pendiente</th></tr></thead>
                <tbody>
                @foreach($compras_por_proveedor as $p)
                <tr>
                    <td>{{ $p['nombre'] }}<span class="fila-sub">{{ $nFmt($p['count']) }} compras</span></td>
                    <td class="num">{{ $fmt($p['total']) }}</td>
                    <td class="num">{{ $fmt($p['pagado']) }}</td>
                    <td class="num {{ $p['pendiente'] > 0 ? 'neg' : 'muted' }}">{{ $p['pendiente'] > 0 ? $fmt($p['pendiente']) : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    <div class="foot">
        <span>{{ $razonSocial }} · Reporte de cierre del período</span>
        <span>Generado por VentoryPOS el {{ $generado }}</span>
    </div>

</div>

<script>
    // Abrir el diálogo de impresión automáticamente (elegir "Guardar como PDF").
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>
