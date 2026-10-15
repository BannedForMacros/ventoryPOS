@php
    $s    = fn ($v) => 'S/ ' . number_format((float) $v, 2, '.', ',');
    $cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
    $abrev = fn ($u) => match (mb_strtolower(trim($u))) {
        'unidad', 'unidades' => 'und', 'kilogramo', 'kilogramos' => 'kg', 'gramo', 'gramos' => 'g',
        'litro', 'litros' => 'L', 'mililitro' => 'ml', 'metro', 'metros' => 'm', 'caja' => 'caja',
        default => mb_strtolower(mb_substr($u, 0, 4)),
    };
    $nombreEmpresa =$empresa->nombre_comercial ?: $empresa->razon_social;
    $maxTotal = collect($productos)->max('total') ?: 1;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ventas ítem por ítem · {{ $periodo }}</title>
<style>
    @page { margin: 26px 30px 40px 30px; }
    body, div, p, table, td, th, span { margin: 0; padding: 0; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.2px; color: #1e293b; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .r { text-align: right; } .c { text-align: center; }
    .muted { color: #64748b; } .b { font-weight: bold; }
    .num { white-space: nowrap; }

    .cab { background: #0F4C81; border-radius: 10px; }
    .cab td { vertical-align: middle; padding: 12px 14px; }
    .cab .logo-box { background: #fff; border-radius: 8px; padding: 5px 7px; display: inline-block; }
    .cab .logo-box img { max-height: 34px; max-width: 110px; }
    .cab .emp { color: #fff; font-size: 14px; font-weight: bold; }
    .cab .emp-sub { color: #bcd3ec; font-size: 8px; margin-top: 1px; }
    .cab .tit { color: #fff; font-size: 7.6px; letter-spacing: 1.6px; font-weight: bold; }
    .cab .per { color: #fff; font-size: 14px; font-weight: bold; margin-top: 2px; }
    .cab .gen { color: #bcd3ec; font-size: 7.4px; margin-top: 2px; }
    .franja { height: 3px; background: #00C48C; width: 22%; margin: 0 0 0 14px; }
    .filtros { margin-top: 8px; border: 1px solid #cfe1f6; background: #f2f7fd; border-radius: 7px; padding: 5px 9px; color: #0F4C81; font-size: 8px; }
    .sep { height: 10px; }
    .gap td.g { width: 9px; }

    .kpi { border: 1px solid #d9e2ec; border-radius: 9px; padding: 9px 10px; background: #fff; }
    .kpi .l { font-size: 6.9px; text-transform: uppercase; letter-spacing: .8px; color: #64748b; font-weight: bold; }
    .kpi .v { font-size: 14px; font-weight: bold; color: #0f172a; margin-top: 3px; white-space: nowrap; }
    .kpi .s { font-size: 7.2px; color: #64748b; margin-top: 3px; }
    .kpi.hero { background: #0F4C81; border-color: #0F4C81; }
    .kpi.hero .l { color: #bcd3ec; } .kpi.hero .v { color: #fff; font-size: 16px; } .kpi.hero .s { color: #d6e4f3; }

    .sec { font-size: 10px; font-weight: bold; color: #0F4C81; padding-bottom: 5px; margin-bottom: 6px; border-bottom: 2px solid #0F4C81; }
    .sec .sub { float: right; font-size: 7.4px; font-weight: normal; color: #94a3b8; padding-top: 2px; }

    .tb { border: 1px solid #d9e2ec; }
    .tb thead { display: table-header-group; }
    .tb tr { page-break-inside: avoid; }
    .tb th { background: #f1f5f9; color: #334155; font-size: 6.8px; text-transform: uppercase; letter-spacing: .4px; padding: 5px 5px; text-align: left; border-bottom: 1px solid #d9e2ec; }
    .tb th.r { text-align: right; } .tb th.c { text-align: center; }
    .tb td { padding: 4px 5px; border-bottom: 1px solid #edf1f5; vertical-align: middle; }
    .tb tr.par td { background: #fafcfe; }
    .tb tfoot td { background: #e8f1fb; color: #0F4C81; font-weight: bold; border-top: 1px solid #b9d0ea; border-bottom: 0; }
    .tb tr.nueva td { border-top: 1px solid #d9e2ec; }
    .tb tr.dg td { color: #b45309; font-style: italic; background: #fffaf2; }
    .bar-mini { background: #edf2f7; height: 4px; border-radius: 2px; margin-top: 2px; }
    .bar-mini div { background: #1A73C8; height: 4px; border-radius: 2px; }
    .desc { color: #b45309; }

    .dia { margin-top: 10px; background: #0F4C81; color: #fff; border-radius: 6px 6px 0 0; }
    .dia td { padding: 5px 8px; font-weight: bold; vertical-align: middle; }
    .dia .s { color: #bcd3ec; font-weight: normal; font-size: 7.4px; }

    .cuadre td { padding: 3px 0; font-size: 8.4px; }
    .cuadre tr.tot td { border-top: 1px solid #0F4C81; font-weight: bold; color: #0F4C81; padding-top: 5px; font-size: 9.4px; }
    .aviso { border: 1px solid #fcd9a8; background: #fffaf2; border-radius: 7px; padding: 6px 9px; color: #92400e; font-size: 7.8px; }
    .notas { margin-top: 10px; font-size: 6.9px; color: #94a3b8; line-height: 1.5; }
    .pie { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    .pagina:after { content: counter(page); }
    .salto { page-break-before: always; }
</style>
</head>
<body>
    <div class="pie">
        <table><tr>
            <td>{{ $empresa->razon_social }}@if($empresa->ruc) · RUC {{ $empresa->ruc }}@endif · Ventas ítem por ítem · {{ $periodo }}</td>
            <td class="r">Generado el {{ $generado }} · ventoryPOS · pág. <span class="pagina"></span></td>
        </tr></table>
    </div>

    {{-- ══ Cabecera ══════════════════════════════════════════════════ --}}
    <table class="cab"><tr>
        <td style="width: 58%;">
            <table><tr>
                @if($logo)
                    <td style="width: 1%; padding: 0 10px 0 0;"><span class="logo-box"><img src="{{ $logo }}" alt=""></span></td>
                @endif
                <td style="padding: 0;">
                    <div class="emp">{{ $nombreEmpresa }}</div>
                    <div class="emp-sub">{{ $empresa->razon_social }}@if($empresa->ruc) · RUC {{ $empresa->ruc }}@endif</div>
                </td>
            </tr></table>
        </td>
        <td class="r">
            <div class="tit">REPORTE DE VENTAS · ÍTEM POR ÍTEM</div>
            <div class="per">{{ $periodo }}</div>
            <div class="gen">Solo ventas completadas · montos en soles</div>
        </td>
    </tr></table>
    <div class="franja"></div>

    @if(count($filtros))
        <div class="filtros"><b>Filtros:</b> {{ implode(' · ', $filtros) }}</div>
    @endif

    <div class="sep"></div>

    {{-- ══ Resumen ═══════════════════════════════════════════════════ --}}
    <table class="gap"><tr>
        <td style="width: 28%;"><div class="kpi hero">
            <div class="l">Total vendido</div>
            <div class="v">{{ $s($k['total']) }}</div>
            <div class="s">sin IGV {{ $s($k['base']) }} · IGV {{ $s($k['igv']) }}</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 24%;"><div class="kpi">
            <div class="l">Ventas</div>
            <div class="v">{{ number_format($k['ventas']) }}</div>
            <div class="s">comprobantes / tickets</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 24%;"><div class="kpi">
            <div class="l">Productos distintos</div>
            <div class="v">{{ number_format($k['productos']) }}</div>
            <div class="s">{{ number_format($k['lineas']) }} ítems vendidos</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 24%;"><div class="kpi">
            <div class="l">Descuentos</div>
            <div class="v">{{ $s($k['desc_items'] + $k['desc_global']) }}</div>
            <div class="s">ya restados del total</div>
        </div></td>
    </tr></table>

    @if($k['lineas'] === 0)
        <div class="sep"></div>
        <div class="aviso">No hay ventas completadas en este período con los filtros elegidos.</div>
    @else

    <div class="sep"></div>

    {{-- ══ 1. Consolidado por producto ═══════════════════════════════ --}}
    <div class="sec">1. Resumen por producto <span class="sub">ordenado de mayor a menor venta</span></div>
    <table class="tb">
        <thead><tr>
            <th class="c" style="width: 4%;">#</th>
            <th>Producto</th>
            <th style="width: 9%;">Unidad</th>
            <th class="r" style="width: 9%;">Cantidad</th>
            <th class="r" style="width: 7%;">Ventas</th>
            <th class="r" style="width: 12%;">Precio prom.</th>
            <th class="r" style="width: 15%;">Total</th>
            <th class="r" style="width: 7%;">% total</th>
        </tr></thead>
        <tbody>
            @foreach($productos as $i => $p)
                @php $pct = $k['suma_lineas'] > 0 ? $p['total'] / $k['suma_lineas'] * 100 : 0; @endphp
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td class="c muted">{{ $i + 1 }}</td>
                    <td>
                        {{ $p['nombre'] }}
                        <div class="bar-mini"><div style="width: {{ max(1, round($p['total'] / $maxTotal * 100)) }}%;"></div></div>
                    </td>
                    <td class="muted">{{ $p['unidad'] }}</td>
                    <td class="r num">{{ $cant($p['cantidad']) }}</td>
                    <td class="r num">{{ $p['ventas'] }}</td>
                    <td class="r num">{{ $s($p['promedio']) }}@if($p['p_min'] != $p['p_max'])<div class="muted" style="font-size: 6.4px;">{{ number_format($p['p_min'], 2) }} – {{ number_format($p['p_max'], 2) }}</div>@endif</td>
                    <td class="r num b">{{ $s($p['total']) }}</td>
                    <td class="r num muted">{{ number_format($pct, 1) }}%</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr>
            <td></td>
            <td colspan="2">{{ $k['productos'] }} productos</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="r num">{{ $s($k['suma_lineas']) }}</td>
            <td class="r num">100%</td>
        </tr></tfoot>
    </table>

    <div class="sep"></div>

    {{-- Cuadre: suma de ítems → total cobrado --}}
    <table><tr>
        <td style="width: 55%;" class="notas" >
            «Precio prom.» = total ÷ cantidad (si el precio cambió en el período, debajo se ve el mínimo y el máximo).
            Las cantidades se suman por unidad de venta: un mismo producto vendido en otra presentación aparece en otra fila.
        </td>
        <td style="width: 5%;"></td>
        <td>
            <table class="cuadre">
                <tr><td>Suma de los ítems</td><td class="r num">{{ $s($k['suma_lineas']) }}</td></tr>
                @if($k['desc_global'] > 0)
                    <tr><td class="desc">Descuento aplicado a la venta completa</td><td class="r num desc">− {{ $s($k['desc_global']) }}</td></tr>
                @endif
                <tr class="tot"><td>Total vendido</td><td class="r num">{{ $s($k['total']) }}</td></tr>
            </table>
        </td>
    </tr></table>

    @if($devoluciones['n'] > 0)
        <div class="sep"></div>
        <div class="aviso">
            Hubo {{ $devoluciones['n'] }} {{ $devoluciones['n'] === 1 ? 'devolución' : 'devoluciones' }} de estas ventas por {{ $s($devoluciones['monto']) }}.
            Este reporte muestra lo vendido; las devoluciones no se restan aquí.
        </div>
    @endif

    {{-- ══ 2. Detalle línea por línea ════════════════════════════════ --}}
    @if($detalle_pedido)
        <div class="salto"></div>
        <div class="sec">2. Detalle ítem por ítem <span class="sub">cada producto de cada venta, por día</span></div>

        @if($dias === null)
            <div class="aviso">
                El período tiene {{ number_format($k['lineas']) }} ítems (más de {{ number_format($max_lineas) }}): el detalle no entra en un PDF.
                Acorte el rango de fechas para obtenerlo.
            </div>
        @else
            @foreach($dias as $d)
                <table class="dia"><tr>
                    <td>{{ $d['titulo'] }}</td>
                    <td class="r"><span class="s">{{ $d['n_ventas'] }} {{ $d['n_ventas'] === 1 ? 'venta' : 'ventas' }} · </span>{{ $s($d['total']) }}</td>
                </tr></table>
                <table class="tb">
                    <thead><tr>
                        <th style="width: 6%;">Hora</th>
                        <th style="width: 10%;">Nº venta</th>
                        <th>Producto</th>
                        <th class="r" style="width: 10%;">Cant.</th>
                        <th class="r" style="width: 10%;">P. unit.</th>
                        <th class="r" style="width: 9%;">Desc.</th>
                        <th class="r" style="width: 12%;">Importe</th>
                    </tr></thead>
                    <tbody>
                        @foreach($d['lineas'] as $l)
                            <tr class="{{ $l['primera'] && ! $loop->first ? 'nueva' : '' }}">
                                <td class="muted num">{{ $l['primera'] ? $l['hora'] : '' }}</td>
                                <td class="num">@if($l['primera']){{ $l['documento'] }}@endif</td>
                                <td>
                                    {{ $l['producto'] }}
                                    @if($l['primera'] && $l['cliente'])<div class="muted" style="font-size: 6.6px;">Cliente: {{ $l['cliente'] }}</div>@endif
                                </td>
                                <td class="r num">{{ $cant($l['cantidad']) }} <span class="muted">{{ $abrev($l['unidad']) }}</span></td>
                                <td class="r num">{{ number_format($l['precio'], 2) }}</td>
                                <td class="r num {{ $l['descuento'] > 0 ? 'desc' : 'muted' }}">{{ $l['descuento'] > 0 ? '−' . number_format($l['descuento'], 2) : '—' }}</td>
                                <td class="r num b">{{ number_format($l['subtotal'], 2) }}</td>
                            </tr>
                            @if($l['desc_global'] > 0)
                                <tr class="dg">
                                    <td></td><td></td>
                                    <td colspan="4">Descuento a la venta {{ $l['documento'] }}</td>
                                    <td class="r num">−{{ number_format($l['desc_global'], 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot><tr>
                        <td colspan="6">Total del día</td>
                        <td class="r num">{{ $s($d['total']) }}</td>
                    </tr></tfoot>
                </table>
            @endforeach

            <div class="sep"></div>
            <table class="cuadre" style="width: 40%; margin-left: 60%;">
                <tr class="tot"><td>Total del período</td><td class="r num">{{ $s($k['total']) }}</td></tr>
            </table>
        @endif
    @endif
    @endif
</body>
</html>
