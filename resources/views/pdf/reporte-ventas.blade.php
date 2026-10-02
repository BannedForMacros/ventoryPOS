@php
    $s   = fn ($v) => 'S/ ' . number_format((float) $v, 2, '.', ',');
    $s0  = fn ($v) => 'S/ ' . number_format((float) $v, 0, '.', ',');
    $int = fn ($v) => number_format((float) $v, 0, '.', ',');
    $cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    $corto = function ($v) {
        $v = (float) $v;
        if ($v >= 1000000) return number_format($v / 1000000, 1, '.', '') . 'M';
        if ($v >= 1000)    return number_format($v / 1000, $v >= 10000 ? 0 : 1, '.', '') . 'k';
        return number_format($v, 0, '.', '');
    };
    // Chip de variación vs período anterior.
    $chip = function (?float $p, bool $claro = false) {
        if ($p === null) return '<span class="chip ' . ($claro ? 'chip-claro' : 'chip-neutro') . '">sin período previo</span>';
        $sube = $p >= 0;
        $cls  = $claro ? 'chip-claro' : ($sube ? 'chip-sube' : 'chip-baja');
        return '<span class="chip ' . $cls . '">' . ($sube ? '▲' : '▼') . ' ' . number_format(abs($p), 1) . '%</span>';
    };
    $nombreEmpresa = $empresa->nombre_comercial ?: $empresa->razon_social;
    $alturaBarras  = 92;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de ventas · {{ $periodo }}</title>
<style>
    @page { margin: 26px 30px 40px 30px; }
    body, div, p, table, td, th, span { margin: 0; padding: 0; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.4px; color: #1e293b; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .r { text-align: right; } .c { text-align: center; }
    .muted { color: #64748b; } .b { font-weight: bold; }
    .num { white-space: nowrap; }

    /* Cabecera */
    .cab { background: #0F4C81; border-radius: 10px; }
    .cab td { vertical-align: middle; padding: 12px 14px; }
    .cab .logo-box { background: #fff; border-radius: 8px; padding: 5px 7px; display: inline-block; }
    .cab .logo-box img { max-height: 34px; max-width: 110px; }
    .cab .emp { color: #fff; font-size: 14px; font-weight: bold; }
    .cab .emp-sub { color: #bcd3ec; font-size: 8px; margin-top: 1px; }
    .cab .tit { color: #fff; font-size: 7.6px; letter-spacing: 1.6px; font-weight: bold; }
    .cab .per { color: #fff; font-size: 15px; font-weight: bold; margin-top: 2px; }
    .cab .gen { color: #bcd3ec; font-size: 7.4px; margin-top: 2px; }
    .franja { height: 3px; background: #00C48C; width: 22%; margin: 0 0 0 14px; }

    .filtros { margin-top: 8px; border: 1px solid #cfe1f6; background: #f2f7fd; border-radius: 7px; padding: 5px 9px; color: #0F4C81; font-size: 8px; }

    /* Tarjetas */
    .card { border: 1px solid #d9e2ec; border-radius: 9px; padding: 10px 11px; background: #fff; }
    .card-t { font-size: 7.4px; text-transform: uppercase; letter-spacing: 1px; color: #0F4C81; font-weight: bold; padding-bottom: 6px; margin-bottom: 7px; border-bottom: 1px solid #e6edf4; }
    .card-t .sub { float: right; text-transform: none; letter-spacing: 0; color: #94a3b8; font-weight: normal; }
    .sep { height: 9px; }
    .gap td.g { width: 9px; }
    .nobreak { page-break-inside: avoid; }

    /* KPIs principales */
    .kpi { border: 1px solid #d9e2ec; border-radius: 9px; padding: 9px 10px; background: #fff; }
    .kpi .l { font-size: 6.9px; text-transform: uppercase; letter-spacing: .8px; color: #64748b; font-weight: bold; }
    .kpi .v { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 3px; white-space: nowrap; }
    .kpi .s { font-size: 7.4px; color: #64748b; margin-top: 3px; }
    .kpi.hero { background: #0F4C81; border-color: #0F4C81; }
    .kpi.hero .l { color: #bcd3ec; } .kpi.hero .v { color: #fff; font-size: 17px; } .kpi.hero .s { color: #d6e4f3; }
    .kpi.verde { border-color: #9be3c8; background: #f1fbf7; }
    .kpi.verde .v { color: #047857; }
    .chip { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7px; font-weight: bold; }
    .chip-sube { background: #dcfce7; color: #15803d; }
    .chip-baja { background: #fee2e2; color: #b91c1c; }
    .chip-neutro { background: #f1f5f9; color: #64748b; }
    .chip-claro { background: #1A73C8; color: #fff; }

    /* Franja secundaria */
    .mini { border: 1px solid #d9e2ec; border-radius: 9px; }
    .mini td { padding: 7px 9px; border-left: 1px solid #e6edf4; width: 20%; }
    .mini td:first-child { border-left: 0; }
    .mini .l { font-size: 6.8px; text-transform: uppercase; letter-spacing: .7px; color: #64748b; font-weight: bold; }
    .mini .v { font-size: 10.5px; font-weight: bold; color: #0f172a; margin-top: 2px; white-space: nowrap; }
    .mini .s { font-size: 7px; color: #94a3b8; margin-top: 1px; }
    .rojo { color: #b91c1c !important; } .ambar { color: #a16207 !important; } .verde-t { color: #047857 !important; }

    /* Claves */
    .clave td { padding: 3px 0; font-size: 8.4px; vertical-align: top; }
    .dot { width: 7px; height: 7px; border-radius: 4px; margin-top: 2px; }
    .dot-bien { background: #00C48C; } .dot-alerta { background: #F59E0B; } .dot-info { background: #1A73C8; }

    /* Barras horizontales */
    .hb td { padding: 3.5px 0; vertical-align: middle; }
    .hb .nom { width: 34%; padding-right: 6px; }
    .hb .val { width: 25%; text-align: right; white-space: nowrap; padding-left: 6px; }
    .track { background: #edf2f7; border-radius: 4px; height: 9px; }
    .fill  { background: #1A73C8; border-radius: 4px; height: 9px; }
    .fill-v { background: #00C48C; } .fill-n { background: #0F4C81; } .fill-a { background: #F59E0B; }

    /* Barras verticales */
    .vb td { vertical-align: bottom; text-align: center; padding: 0 1px; }
    .vb .col { background: #1A73C8; border-radius: 2px 2px 0 0; margin: 0 auto; }
    .vb .col.finde { background: #7fb2e5; }
    .vb .col.top { background: #00C48C; }
    .vb .eje td { border-top: 1px solid #cbd5e1; padding-top: 2px; font-size: 6.2px; color: #64748b; vertical-align: top; }
    .vb .lbl td { font-size: 6.2px; color: #0f172a; font-weight: bold; padding-bottom: 1px; }
    .guia { font-size: 6.4px; color: #94a3b8; }

    /* Mapa de calor */
    .heat td { padding: 0; text-align: center; vertical-align: middle; }
    .heat .cel { height: 13px; border: 1px solid #fff; }
    .heat .dia { font-size: 7px; color: #475569; font-weight: bold; text-align: left; padding-right: 4px; width: 26px; }
    .heat .hh td { font-size: 6px; color: #94a3b8; padding-top: 2px; }
    .leyenda td { font-size: 6.4px; color: #94a3b8; vertical-align: middle; }
    .leyenda .sw { width: 14px; height: 7px; }

    /* Tablas con borde */
    .tb { border: 1px solid #d9e2ec; }
    .tb th { background: #f1f5f9; color: #334155; font-size: 6.9px; text-transform: uppercase; letter-spacing: .5px; padding: 5px 6px; text-align: left; border-bottom: 1px solid #d9e2ec; }
    .tb th.r { text-align: right; } .tb th.c { text-align: center; }
    .tb td { padding: 4.5px 6px; border-bottom: 1px solid #edf1f5; vertical-align: middle; }
    .tb tr.par td { background: #fafcfe; }
    .tb tfoot td { background: #f1f5f9; font-weight: bold; border-top: 1px solid #cbd5e1; border-bottom: 0; }
    .rank { width: 15px; height: 11px; padding-top: 4px; margin: 0 auto; border-radius: 8px; background: #e8f1fb; color: #0F4C81; font-weight: bold; text-align: center; font-size: 6.8px; line-height: 1; }
    .rank.oro { background: #0F4C81; color: #fff; }
    .bar-mini { background: #edf2f7; height: 5px; border-radius: 3px; margin-top: 2px; }
    .bar-mini div { background: #1A73C8; height: 5px; border-radius: 3px; }
    .cat { font-size: 6.8px; color: #94a3b8; }

    .aviso { border: 1px solid #fcd9a8; background: #fffaf2; border-radius: 7px; padding: 6px 9px; color: #92400e; font-size: 7.6px; }
    .notas { margin-top: 10px; font-size: 6.9px; color: #94a3b8; line-height: 1.5; }
    .pie { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    .pagina:after { content: counter(page); }
    .salto { page-break-before: always; }
</style>
</head>
<body>
    <div class="pie">
        <table><tr>
            <td>{{ $empresa->razon_social }}@if($empresa->ruc) · RUC {{ $empresa->ruc }}@endif · Reporte de ventas · {{ $periodo }}</td>
            <td class="r">Generado el {{ $generado }} · ventoryPOS · pág. <span class="pagina"></span></td>
        </tr></table>
    </div>

    {{-- ══ Cabecera ══════════════════════════════════════════════════ --}}
    <table class="cab"><tr>
        <td style="width: 60%;">
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
            <div class="tit">REPORTE DE VENTAS</div>
            <div class="per">{{ $periodo }}</div>
            <div class="gen">Del {{ $desde }} al {{ $hasta }}</div>
            <div class="gen">Comparado con {{ $anterior['etiqueta'] }}</div>
        </td>
    </tr></table>
    <div class="franja"></div>

    @if($alcance || count($filtros))
        <div class="filtros">
            @if($alcance)<b>Incluye:</b> {{ $alcance }}@endif
            @if(count($filtros))@if($alcance) · @endif<b>Filtros:</b> {{ implode(' · ', $filtros) }}@endif
        </div>
    @endif

    <div class="sep"></div>

    {{-- ══ KPIs principales ══════════════════════════════════════════ --}}
    <table class="gap"><tr>
        <td style="width: 27%;"><div class="kpi hero">
            <div class="l">Total vendido</div>
            <div class="v">{{ $s($k['total']) }}</div>
            <div class="s">{!! $chip($var['total']) !!} &nbsp;antes {{ $s0($anterior['total']) }}</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 25%;"><div class="kpi verde">
            <div class="l">Utilidad bruta</div>
            <div class="v">{{ $s($k['utilidad']) }}</div>
            <div class="s">{!! $chip($var['utilidad']) !!} &nbsp;margen {{ $k['margen'] !== null ? $k['margen'] . '%' : '—' }}</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 23%;"><div class="kpi">
            <div class="l">Ventas realizadas</div>
            <div class="v">{{ $int($k['n']) }}</div>
            <div class="s">{!! $chip($var['n']) !!} &nbsp;antes {{ $int($anterior['n']) }}</div>
        </div></td>
        <td class="g"></td>
        <td style="width: 25%;"><div class="kpi">
            <div class="l">Ticket promedio</div>
            <div class="v">{{ $s($k['ticket']) }}</div>
            <div class="s">{!! $chip($var['ticket']) !!} &nbsp;antes {{ $s0($anterior['ticket']) }}</div>
        </div></td>
    </tr></table>

    <div class="sep"></div>

    <table class="mini"><tr>
        <td>
            <div class="l">Descuentos dados</div>
            <div class="v {{ $k['desc_pct'] >= 5 ? 'ambar' : '' }}">{{ $s($k['descuentos']) }}</div>
            <div class="s">{{ $k['desc_pct'] }}% del precio de lista · {{ $k['con_desc_n'] }} ventas</div>
        </td>
        <td>
            <div class="l">Por cobrar (crédito)</div>
            <div class="v {{ $k['por_cobrar'] > 0 ? 'ambar' : '' }}">{{ $s($k['por_cobrar']) }}</div>
            <div class="s">vendido al crédito {{ $s0($k['credito']) }}</div>
        </td>
        <td>
            <div class="l">Clientes atendidos</div>
            <div class="v">{{ $int($k['clientes']) }}</div>
            <div class="s">con nombre o RUC/DNI</div>
        </td>
        <td>
            <div class="l">Valor de venta</div>
            <div class="v">{{ $s($k['base']) }}</div>
            <div class="s">sin IGV · IGV {{ $s0($k['igv']) }}</div>
        </td>
        <td>
            <div class="l">Anuladas</div>
            <div class="v {{ $k['anuladas_n'] > 0 ? 'rojo' : '' }}">{{ $int($k['anuladas_n']) }}</div>
            <div class="s">{{ $s($k['anuladas']) }} · no suman</div>
        </td>
    </tr></table>

    <div class="sep"></div>

    {{-- ══ Lo más importante ═════════════════════════════════════════ --}}
    <div class="card nobreak">
        <div class="card-t">Lo más importante del período</div>
        <table class="clave">
            @foreach($claves as $c)
                @if(is_array($c))
                    <tr><td style="width: 12px;"><div class="dot dot-{{ $c['tono'] }}"></div></td><td>{{ $c['texto'] }}</td></tr>
                @else
                    <tr><td>{{ $c }}</td></tr>
                @endif
            @endforeach
        </table>
        @if($k['sin_costo_pct'] >= 5)
            <div class="aviso" style="margin-top: 6px;">
                Ojo con la utilidad: el {{ $k['sin_costo_pct'] }}% de lo vendido ({{ $s($k['sin_costo']) }}) es de productos sin costo registrado,
                así que la utilidad real es menor a la mostrada. Cargar sus costos (entradas o inventario inicial) la corrige.
            </div>
        @endif
    </div>

    <div class="sep"></div>

    {{-- ══ Evolución de ventas ═══════════════════════════════════════ --}}
    @php
        $pts = $serie['puntos'];
        $maxS = $serie['max'] ?: 1;
        $idxTop = collect($pts)->search(fn ($p) => $p['total'] == $serie['max'] && $p['total'] > 0);
        $tituloSerie = ['dia' => 'Ventas por día', 'semana' => 'Ventas por semana', 'mes' => 'Ventas por mes'][$serie['grupo']];
    @endphp
    <div class="card nobreak">
        <div class="card-t">{{ $tituloSerie }} <span class="sub">la barra verde es el {{ $serie['grupo'] === 'mes' ? 'mejor mes' : ($serie['grupo'] === 'semana' ? 'mejor semana' : 'mejor día') }}@if($serie['grupo'] === 'dia') · celeste = fin de semana @endif</span></div>
        <table>
            <tr>
                <td style="width: 34px; vertical-align: top;">
                    <div class="guia" style="height: {{ $alturaBarras / 2 }}px;">{{ $corto($maxS) }}</div>
                    <div class="guia" style="height: {{ $alturaBarras / 2 }}px;">{{ $corto($maxS / 2) }}</div>
                </td>
                <td>
                    <table class="vb">
                        <tr class="lbl">
                            @foreach($pts as $i => $p)
                                <td>@if($i === $idxTop){{ $corto($p['total']) }}@endif</td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($pts as $i => $p)
                                @php $h = $p['total'] > 0 ? max(2, round($p['total'] / $maxS * $alturaBarras)) : 0; @endphp
                                <td style="height: {{ $alturaBarras }}px;">
                                    @if($h > 0)<div class="col {{ $i === $idxTop ? 'top' : ($p['finde'] ? 'finde' : '') }}" style="height: {{ $h }}px; width: {{ count($pts) > 20 ? 70 : 60 }}%;"></div>@endif
                                </td>
                            @endforeach
                        </tr>
                        <tr class="eje">
                            @foreach($pts as $p)
                                <td>{{ $p['etiqueta'] }}@if($p['sub'])<br>{{ $p['sub'] }}@endif</td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ══ Cobro y cuándo vende ═════════════════════════════════════ --}}
    <div class="sep"></div>

    <table class="gap"><tr>
        <td style="width: 55%;"><div class="card nobreak">
            <div class="card-t">Cómo le pagaron <span class="sub">lo cobrado de estas ventas</span></div>
            <table class="hb">
                @forelse($metodos as $m)
                    <tr>
                        <td class="nom"><b>{{ $m['nombre'] }}</b><br><span class="muted">{{ $m['n'] }} {{ $m['n'] === 1 ? 'venta' : 'ventas' }}</span></td>
                        <td><div class="track"><div class="fill {{ $loop->first ? 'fill-n' : '' }}" style="width: {{ max(1, $m['pct']) }}%;"></div></div></td>
                        <td class="val"><b>{{ $s($m['total']) }}</b><br><span class="muted">{{ $m['pct'] }}%</span></td>
                    </tr>
                @empty
                    <tr><td class="muted">Sin cobros registrados.</td></tr>
                @endforelse
                @if($k['por_cobrar'] > 0)
                    <tr>
                        <td class="nom"><b class="ambar">Por cobrar</b><br><span class="muted">saldo de créditos</span></td>
                        <td><div class="track"><div class="fill fill-a" style="width: {{ $k['total'] > 0 ? max(1, round($k['por_cobrar'] / $k['total'] * 100, 1)) : 0 }}%;"></div></div></td>
                        <td class="val"><b class="ambar">{{ $s($k['por_cobrar']) }}</b></td>
                    </tr>
                @endif
            </table>
        </div></td>
        <td class="g"></td>
        <td style="width: 45%;">
            <div class="card nobreak">
                <div class="card-t">Contado y crédito</div>
                @php $pc = $k['total'] > 0 ? round($k['contado'] / $k['total'] * 100, 1) : 0; @endphp
                <table style="height: 12px;"><tr>
                    @if($pc > 0)<td style="width: {{ $pc }}%; background: #0F4C81; height: 12px; border-radius: 4px 0 0 4px;"></td>@endif
                    @if($pc < 100)<td style="background: #F59E0B; height: 12px; border-radius: 0 4px 4px 0;"></td>@endif
                </tr></table>
                <table style="margin-top: 6px;"><tr>
                    <td><span class="b" style="color: #0F4C81;">■</span> Contado<br><b>{{ $s($k['contado']) }}</b> <span class="muted">{{ $pc }}%</span></td>
                    <td class="r"><span class="b" style="color: #F59E0B;">■</span> Crédito<br><b>{{ $s($k['credito']) }}</b> <span class="muted">{{ round(100 - $pc, 1) }}%</span></td>
                </tr></table>
            </div>
            <div class="sep"></div>
            <div class="card nobreak">
                <div class="card-t">Comprobantes emitidos</div>
                <table class="hb">
                    @foreach($comprobantes as $c)
                        <tr>
                            <td class="nom" style="width: 40%;">{{ $c['nombre'] }} <span class="muted">({{ $c['n'] }})</span></td>
                            <td><div class="track"><div class="fill fill-v" style="width: {{ max(1, $c['pct']) }}%;"></div></div></td>
                            <td class="val">{{ $s0($c['total']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </td>
    </tr></table>

    <div class="sep"></div>

    <table class="gap"><tr>
        <td style="width: 50%;"><div class="card nobreak">
            <div class="card-t">Día de la semana <span class="sub">promedio vendido por día</span></div>
            @php $maxSem = $semana['max'] ?: 1; $topSem = collect($semana['filas'])->sortByDesc('promedio')->keys()->first(); @endphp
            <table class="hb">
                @foreach($semana['filas'] as $i => $d)
                    <tr>
                        <td class="nom" style="width: 22%;"><b>{{ $d['nombre'] }}</b></td>
                        <td><div class="track"><div class="fill {{ $i === $topSem && $d['promedio'] > 0 ? 'fill-v' : '' }}" style="width: {{ $d['promedio'] > 0 ? max(1, round($d['promedio'] / $maxSem * 100, 1)) : 0 }}%;"></div></div></td>
                        <td class="val" style="width: 30%;"><b>{{ $s0($d['promedio']) }}</b><br><span class="muted">total {{ $s0($d['total']) }}</span></td>
                    </tr>
                @endforeach
            </table>
        </div></td>
        <td class="g"></td>
        <td style="width: 50%;"><div class="card nobreak">
            <div class="card-t">Horario de ventas <span class="sub">vendido por hora</span></div>
            @php $maxH = $horas['max'] ?: 1; $topH = collect($horas['filas'])->sortByDesc('total')->keys()->first(); @endphp
            @if(count($horas['filas']))
                <table class="vb">
                    <tr class="lbl">
                        @foreach($horas['filas'] as $i => $h)<td>@if($i === $topH){{ $corto($h['total']) }}@endif</td>@endforeach
                    </tr>
                    <tr>
                        @foreach($horas['filas'] as $i => $h)
                            @php $alto = $h['total'] > 0 ? max(2, round($h['total'] / $maxH * 118)) : 0; @endphp
                            <td style="height: 118px;">@if($alto)<div class="col {{ $i === $topH ? 'top' : '' }}" style="height: {{ $alto }}px; width: 70%;"></div>@endif</td>
                        @endforeach
                    </tr>
                    <tr class="eje">
                        @foreach($horas['filas'] as $h)<td>{{ $h['h'] % 12 === 0 ? 12 : $h['h'] % 12 }}<br>{{ $h['h'] < 12 ? 'am' : 'pm' }}</td>@endforeach
                    </tr>
                </table>
            @else
                <div class="muted">Sin ventas.</div>
            @endif
        </div></td>
    </tr></table>

    <div class="sep"></div>

    @if(count($mapa['filas']))
        <div class="card nobreak">
            <div class="card-t">¿Cuándo se vende más? <span class="sub">día de la semana × hora · más oscuro = más venta</span></div>
            <table class="heat">
                @foreach($mapa['filas'] as $f)
                    <tr>
                        <td class="dia">{{ $f['nombre'] }}</td>
                        @foreach($f['cols'] as $col)<td><div class="cel" style="background: {{ $col['color'] }};"></div></td>@endforeach
                    </tr>
                @endforeach
                <tr class="hh">
                    <td></td>
                    @foreach($mapa['horas'] as $h)<td>{{ $h % 12 === 0 ? 12 : $h % 12 }}{{ $h < 12 ? 'a' : 'p' }}</td>@endforeach
                </tr>
            </table>
            <table class="leyenda" style="width: auto; margin-top: 5px;"><tr>
                <td style="padding-right: 4px;">Menos</td>
                @foreach(['#f4f7fb', '#c9dcf1', '#8fb5dd', '#4f86bd', '#0F4C81'] as $sw)<td><div class="sw" style="background: {{ $sw }};"></div></td>@endforeach
                <td style="padding-left: 4px;">Más</td>
            </tr></table>
        </div>
    @endif

    {{-- ══ Qué y a quién ════════════════════════════════════════════ --}}
    <div class="sep"></div>

    <div class="card nobreak">
        <div class="card-t">Los 10 productos que más vendieron <span class="sub">por monto</span></div>
        <table class="tb">
            <thead><tr>
                <th class="c" style="width: 20px;">#</th><th>Producto</th><th class="r">Cantidad</th>
                <th class="r">Vendido</th><th style="width: 70px;">% del total</th><th class="r">Utilidad</th><th class="r">Margen</th>
            </tr></thead>
            <tbody>
                @forelse($top as $i => $p)
                    <tr class="{{ $i % 2 ? 'par' : '' }}">
                        <td class="c"><div class="rank {{ $i === 0 ? 'oro' : '' }}">{{ $i + 1 }}</div></td>
                        <td><b>{{ $p['nombre'] }}</b><br><span class="cat">{{ $p['categoria'] }}</span></td>
                        <td class="r num">{{ $cant($p['cantidad']) }} <span class="muted">{{ mb_strtolower($p['unidad']) }}</span></td>
                        <td class="r num b">{{ $s($p['total']) }}</td>
                        <td>{{ $p['pct'] }}%<div class="bar-mini"><div style="width: {{ min(100, max(2, $p['pct'])) }}%;"></div></div></td>
                        <td class="r num {{ $p['utilidad'] < 0 ? 'rojo' : '' }}">{{ $s($p['utilidad']) }}</td>
                        <td class="r num">{{ $p['margen'] !== null ? $p['margen'] . '%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin ventas.</td></tr>
                @endforelse
            </tbody>
            @if(count($top))
                <tfoot><tr>
                    <td></td><td>Estos 10 productos</td><td></td>
                    <td class="r num">{{ $s(collect($top)->sum('total')) }}</td>
                    <td>{{ round(collect($top)->sum('pct'), 1) }}%</td>
                    <td class="r num">{{ $s(collect($top)->sum('utilidad')) }}</td><td></td>
                </tr></tfoot>
            @endif
        </table>
    </div>

    <div class="sep"></div>

    <table class="gap"><tr>
        <td style="width: 46%;"><div class="card nobreak">
            <div class="card-t">Ventas por categoría</div>
            <table class="hb">
                @foreach($categorias as $c)
                    <tr>
                        <td class="nom" style="width: 38%;"><b>{{ $c['nombre'] }}</b><br><span class="muted">margen {{ $c['margen'] !== null ? $c['margen'] . '%' : '—' }}</span></td>
                        <td><div class="track"><div class="fill {{ $loop->first ? 'fill-n' : '' }}" style="width: {{ max(1, $c['pct']) }}%;"></div></div></td>
                        <td class="val" style="width: 28%;"><b>{{ $s0($c['total']) }}</b><br><span class="muted">{{ $c['pct'] }}%</span></td>
                    </tr>
                @endforeach
            </table>
        </div></td>
        <td class="g"></td>
        <td style="width: 54%;"><div class="card nobreak">
            <div class="card-t">Mejores clientes</div>
            <table class="tb">
                <thead><tr><th class="c" style="width: 18px;">#</th><th>Cliente</th><th class="c">Compras</th><th class="r">Monto</th><th class="r">%</th></tr></thead>
                <tbody>
                    @forelse($clientes['top'] as $i => $c)
                        <tr class="{{ $i % 2 ? 'par' : '' }}">
                            <td class="c"><div class="rank {{ $i === 0 ? 'oro' : '' }}">{{ $i + 1 }}</div></td>
                            <td>{{ $c['nombre'] }}</td><td class="c">{{ $c['n'] }}</td>
                            <td class="r num b">{{ $s($c['total']) }}</td><td class="r">{{ $c['pct'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Todas las ventas fueron a clientes varios.</td></tr>
                    @endforelse
                </tbody>
                @if($clientes['varios_n'] > 0)
                    <tfoot><tr>
                        <td></td><td>Clientes varios (sin identificar)</td><td class="c">{{ $clientes['varios_n'] }}</td>
                        <td class="r num">{{ $s($clientes['varios_total']) }}</td><td class="r">{{ $clientes['varios_pct'] }}%</td>
                    </tr></tfoot>
                @endif
            </table>
        </div></td>
    </tr></table>

    <div class="sep"></div>

    <table class="gap"><tr>
        <td style="width: {{ count($desc_conceptos) ? 62 : 100 }}%;"><div class="card nobreak">
            <div class="card-t">Quién vendió</div>
            <table class="tb">
                <thead><tr><th>Vendedor</th><th class="c">Ventas</th><th class="r">Monto</th><th style="width: 60px;">Participación</th><th class="r">Ticket prom.</th><th class="r">Descuentos</th></tr></thead>
                <tbody>
                    @foreach($cajeros as $i => $c)
                        <tr class="{{ $i % 2 ? 'par' : '' }}">
                            <td><b>{{ $c['nombre'] }}</b></td><td class="c">{{ $int($c['n']) }}</td>
                            <td class="r num b">{{ $s($c['total']) }}</td>
                            <td>{{ $c['pct'] }}%<div class="bar-mini"><div style="width: {{ min(100, max(2, $c['pct'])) }}%;"></div></div></td>
                            <td class="r num">{{ $s($c['ticket']) }}</td>
                            <td class="r num {{ $c['descuentos'] > 0 ? 'ambar' : 'muted' }}">{{ $s($c['descuentos']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></td>
        @if(count($desc_conceptos))
            <td class="g"></td>
            <td style="width: 38%;"><div class="card nobreak">
                <div class="card-t">Descuentos por motivo</div>
                @php $maxD = collect($desc_conceptos)->max('total') ?: 1; @endphp
                <table class="hb">
                    @foreach($desc_conceptos as $d)
                        <tr>
                            <td class="nom" style="width: 42%;"><b>{{ $d['nombre'] }}</b><br><span class="muted">{{ $d['n'] }} {{ $d['n'] === 1 ? 'venta' : 'ventas' }}</span></td>
                            <td><div class="track"><div class="fill fill-a" style="width: {{ max(2, round($d['total'] / $maxD * 100)) }}%;"></div></div></td>
                            <td class="val" style="width: 30%;"><b>{{ $s($d['total']) }}</b></td>
                        </tr>
                    @endforeach
                </table>
            </div></td>
        @endif
    </tr></table>

    <div class="notas">
        <b>Cómo leer este reporte.</b> Solo cuentan las ventas completadas; las anuladas se muestran aparte y no suman.
        Los montos incluyen IGV. La utilidad bruta es lo vendido menos el costo de esos productos al momento de venderlos (sin gastos del negocio).
        "Por cobrar" es el saldo pendiente de las ventas a crédito de este período. Las variaciones (▲▼) comparan con {{ $anterior['etiqueta'] }}.
    </div>
</body>
</html>
