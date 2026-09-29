@php
    /** @var array $planilla */
    $cols      = $planilla['columnas'];
    $razon     = $empresa->razon_social ?? $empresa->nombre_comercial ?? 'Empresa';
    $fmt       = fn ($n) => ($n < 0 ? '-' : '') . 'S/ ' . number_format(abs((float) $n), 2, '.', ',');
    $fecha     = \Illuminate\Support\Carbon::parse($turno->fecha_apertura)->locale('es')->translatedFormat('j \d\e F Y');
    // Un tono suave por columna para distinguirlas de un vistazo.
    $tonos     = ['#E6F4D7', '#F9DDE6', '#DCE3F7', '#FDEBD2', '#E0F2F1', '#EDE3F7', '#F3F4F6'];
    $tonoDe    = function (array $c, int $i) use ($tonos) {
        return match ($c['clave']) {
            'credito'  => '#DDF3DC',
            'anticipo' => '#FFF4CC',
            default    => $tonos[$i % count($tonos)],
        };
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte de caja · Turno {{ $turno->id }} · {{ $razon }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 11px; color: #14273B; background: #E9EDF2; }
    .toolbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 14px; padding: 11px 22px; margin-bottom: 16px;
               background: linear-gradient(90deg, #0F4C81, #155fa3); color: #fff; }
    .toolbar .marca { font-weight: 800; font-size: 14px; } .toolbar .marca span { color: #7fd8bb; }
    .toolbar .hint { font-size: 11px; opacity: .85; } .toolbar .spacer { flex: 1; }
    .toolbar a { color: #fff; font-size: 11.5px; text-decoration: none; opacity: .9; }
    .btn { border: none; cursor: pointer; font-family: inherit; background: #00C48C; color: #04301f; font-weight: 800; font-size: 13px;
           padding: 9px 20px; border-radius: 10px; }
    .hoja { width: 297mm; margin: 0 auto 24px; background: #fff; padding: 10mm; }
    h1 { font-size: 15px; font-weight: 800; text-align: center; padding: 6px; background: #D9D2F2; border: 1px solid #9A8FD1; }
    .meta { display: flex; justify-content: space-between; margin: 6px 0 8px; color: #5C6B7C; font-size: 10.5px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #9AA5B1; padding: 3px 6px; }
    th { font-size: 10.5px; font-weight: 800; text-transform: uppercase; text-align: center; }
    td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    td.vale { white-space: nowrap; }
    tr.seccion td { background: #D9D2F2; font-weight: 800; text-transform: uppercase; }
    tr.vacia td { color: #97A3B0; font-style: italic; }
    .neg { color: #C62828; }
    tr.anulada td { color: #C62828; }
    .tag { font-size: 9px; font-weight: 800; color: #C62828; border: 1px solid #C62828; border-radius: 3px; padding: 0 3px; margin-left: 4px; }
    /* Casilla para marcar a mano al revisar cada venta. */
    .rev { text-align: center; }
    .caja { display: inline-block; width: 11px; height: 11px; border: 1.3px solid #5C6B7C; border-radius: 2px; vertical-align: middle; }
    tr.total td { background: #FCE7B2; font-weight: 800; }
    tr.total-lbl td { background: #FCE7B2; font-weight: 800; text-transform: uppercase; text-align: center; font-size: 10px; }
    .aviso { margin-top: 8px; padding: 6px 8px; background: #FBEEEA; color: #C62828; border: 1px solid #F3C2B5; font-weight: 700; }
    .pie { margin-top: 8px; color: #97A3B0; font-size: 9.5px; text-align: right; }
    @media print {
        body { background: none; } .toolbar { display: none !important; }
        .hoja { width: 100%; margin: 0; padding: 0; }
        @page { size: A4 landscape; margin: 9mm; }
        tr { break-inside: avoid; } thead { display: table-header-group; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <span class="marca">Ventory<span>POS</span></span>
    <span class="hint">Reporte de caja del turno #{{ $turno->id }}</span>
    <span class="spacer"></span>
    <a href="javascript:window.close()">← Volver</a>
    <button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>

<div class="hoja">
    <h1>REPORTE DE CAJA {{ mb_strtoupper($fecha) }}</h1>
    <div class="meta">
        <span>{{ $razon }} · {{ $turno->caja?->nombre ?? 'Caja' }}{{ $turno->local ? ' · ' . $turno->local->nombre : '' }}</span>
        <span>Abrió {{ $turno->user?->name ?? '—' }} el {{ \Illuminate\Support\Carbon::parse($turno->fecha_apertura)->format('d/m/Y H:i') }}
            @if($turno->fecha_cierre) · cerró el {{ \Illuminate\Support\Carbon::parse($turno->fecha_cierre)->format('d/m/Y H:i') }} @else · turno abierto @endif</span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="background:#CFE8C3">Forma de pago</th>
                <th style="background:#CFE2F3">Vale</th>
                <th style="background:#F6D5C2">Cliente</th>
                @foreach($cols as $i => $c)
                    <th style="background: {{ $tonoDe($c, $i) }}">{{ $c['nombre'] }}</th>
                @endforeach
                <th style="background:#EDEFF2">Revisado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($planilla['secciones'] as $s)
                <tr class="seccion"><td>{{ $s['titulo'] }}:</td><td></td><td></td>@foreach($cols as $c)<td></td>@endforeach<td></td></tr>
                @forelse($s['filas'] as $f)
                    @php $anulada = $f['anulada'] ?? false; @endphp
                    <tr class="{{ $anulada ? 'anulada' : '' }}">
                        <td>{{ in_array($f['tipo'], ['venta', 'abono'], true) ? '' : $f['vale'] }}</td>
                        <td class="vale">
                            {{ in_array($f['tipo'], ['venta', 'abono'], true) ? $f['vale'] : '' }}
                            @if($anulada)<span class="tag">ANULADA</span>@endif
                        </td>
                        <td>{{ $f['cliente'] }}</td>
                        @foreach($cols as $c)
                            @php $m = $anulada ? 0 : ($f['montos'][$c['clave']] ?? null); @endphp
                            <td class="num {{ ($m ?? 0) < 0 || ($c['clave'] === 'credito' && $m) ? 'neg' : '' }}">{{ $m !== null ? $fmt($m) : '' }}</td>
                        @endforeach
                        <td class="rev">@if(isset($f['venta_id']))<span class="caja"></span>@endif</td>
                    </tr>
                @empty
                    <tr class="vacia"><td></td><td colspan="{{ 2 + count($cols) + 1 }}">Sin movimientos</td></tr>
                @endforelse
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td></td><td></td><td></td>
                @foreach($cols as $c)
                    <td class="num {{ ($planilla['totales'][$c['clave']] ?? 0) < 0 ? 'neg' : '' }}">{{ $fmt($planilla['totales'][$c['clave']] ?? 0) }}</td>
                @endforeach
                <td></td>
            </tr>
            <tr class="total-lbl">
                <td></td><td></td><td></td>
                @foreach($cols as $c)
                    <td>{{ $c['es_efectivo'] ? 'Efectivo en caja' : $c['nombre'] }}</td>
                @endforeach
                <td></td>
            </tr>
        </tfoot>
    </table>

    @if(abs($planilla['diferencia_sistema']) >= 0.01)
        <div class="aviso">
            El sistema espera {{ $fmt($planilla['efectivo_esperado']) }} en efectivo y la planilla suma {{ $fmt($planilla['efectivo_en_caja']) }}
            (diferencia {{ $fmt($planilla['diferencia_sistema']) }}): hay un movimiento de efectivo que la planilla no detalla.
        </div>
    @endif

    <div class="pie">
        Efectivo en caja = inicio + ventas en efectivo + otros ingresos + pagos anteriores − salidas en efectivo ·
        {{ $planilla['ventas_count'] }} ventas para revisar · Generado el {{ $generado }}
    </div>
</div>
<script>window.addEventListener('load', () => { if (!location.hash.includes('noprint')) setTimeout(window.print, 400); });</script>
</body>
</html>
