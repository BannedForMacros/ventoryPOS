@php
    $m = fn ($v) => number_format((float) $v, 2, '.', ',');
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $doc_tipo }} {{ $doc_numero }}</title>
<style>
    body, div, table, td, th, img { margin: 0; padding: 0; box-sizing: border-box; }
    @page { margin: 12px 11px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 7.8px; color: #111; line-height: 1.38; }
    table { width: 100%; border-collapse: collapse; }
    .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
    .muted { color: #555; }
    .logo { max-height: 54px; max-width: 120px; margin-bottom: 4px; }
    .negocio { font-size: 11px; font-weight: bold; }
    .sep { border-top: 1px dashed #777; margin: 6px 0; }
    .doc { border: 1px solid #111; border-radius: 4px; padding: 4px; margin-top: 5px; }
    .doc .tipo { font-size: 8.6px; font-weight: bold; letter-spacing: .5px; }
    .doc .num { font-size: 10px; font-weight: bold; }
    .kv td { padding: .6px 0; vertical-align: top; }
    .kv .k { width: 46px; color: #555; }
    .it td { padding: 1.4px 0; vertical-align: top; }
    .det { font-size: 7px; color: #555; }
    .tot td { padding: 1px 0; }
    .grande td { font-size: 11px; font-weight: bold; padding-top: 3px; }
    .anulada { border: 1.5px solid #b91c1c; color: #b91c1c; text-align: center; font-weight: bold; font-size: 10px; padding: 2px; margin-top: 5px; }
</style>
</head>
<body>
<div class="c">
    @if ($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif
    <div class="negocio">{{ $negocio }}</div>
    @if ($razon && $razon !== $negocio)<div>{{ $razon }}</div>@endif
    @if ($ruc)<div>RUC {{ $ruc }}</div>@endif
    @if ($direccion)<div class="muted">{{ $direccion }}</div>@endif
    @if ($telefono)<div class="muted">Tel. {{ $telefono }}</div>@endif
    <div class="doc">
        <div class="tipo">{{ $doc_tipo }}</div>
        <div class="num">{{ $doc_numero }}</div>
    </div>
    @if ($anulada)<div class="anulada">ANULADA</div>@endif
</div>

<div class="sep"></div>
<table class="kv">
    <tr><td class="k">Fecha</td><td>{{ $fecha }} {{ $hora }}</td></tr>
    <tr><td class="k">Cliente</td><td class="b">{{ $cliente['nombre'] ?? 'Clientes varios' }}</td></tr>
    @if (! empty($cliente['documento']))<tr><td class="k">Doc.</td><td>{{ $cliente['documento'] }}</td></tr>@endif
    @if (! empty($cliente['direccion']))<tr><td class="k">Dirección</td><td>{{ $cliente['direccion'] }}</td></tr>@endif
    @if ($atendido)<tr><td class="k">Atendió</td><td>{{ $atendido }}</td></tr>@endif
</table>

<div class="sep"></div>
<table class="it">
    <tr class="b"><td>Descripción</td><td class="r" style="width: 52px;">Importe</td></tr>
    @foreach ($items as $it)
        <tr>
            <td>
                <span class="b">{{ $it['producto'] }}</span>
                @if ($it['descripcion'])<div class="det">{{ $it['descripcion'] }}</div>@endif
                <div class="det">{{ $q($it['cantidad']) }} {{ $it['unidad'] }} x {{ $m($it['precio']) }}@if ($it['descuento'] > 0) · dscto -{{ $m($it['descuento']) }}@endif</div>
            </td>
            <td class="r">{{ $m($it['importe']) }}</td>
        </tr>
    @endforeach
</table>

<div class="sep"></div>
<table class="tot">
    @if ($mostrar_igv)
        <tr><td>Op. gravada</td><td class="r">{{ $m($gravada) }}</td></tr>
        <tr><td>IGV ({{ $tasa_igv }}%)</td><td class="r">{{ $m($igv) }}</td></tr>
    @endif
    @if ($descuento > 0)
        <tr><td>Descuento</td><td class="r">-{{ $m($descuento) }}</td></tr>
    @endif
    <tr class="grande"><td>TOTAL S/</td><td class="r">{{ $m($total) }}</td></tr>
</table>
<div class="muted" style="margin-top: 3px;">{{ $en_letras }}</div>

@if (count($pagos) || $vuelto > 0)
    <div class="sep"></div>
    <table class="tot">
        @foreach ($pagos as $p)
            <tr><td>{{ $p['metodo'] }}</td><td class="r">{{ $m($p['monto']) }}</td></tr>
        @endforeach
        @if ($vuelto > 0)<tr><td>Vuelto</td><td class="r">{{ $m($vuelto) }}</td></tr>@endif
    </table>
@endif

@if ($credito)
    <div class="sep"></div>
    <div class="b">VENTA AL CRÉDITO</div>
    <table class="tot">
        <tr><td>Pagado</td><td class="r">{{ $m($credito['pagado']) }}</td></tr>
        <tr class="b"><td>Saldo</td><td class="r">{{ $m($credito['saldo']) }}</td></tr>
        @if ($credito['vence'])<tr><td>Vence</td><td class="r">{{ $credito['vence'] }}</td></tr>@endif
    </table>
@endif

@if (count($pendientes))
    <div class="sep"></div>
    <div class="b">PENDIENTE POR ENTREGAR (pagado)</div>
    @foreach ($pendientes as $p)
        <div>{{ $q($p['pendiente']) }} {{ $p['unidad'] }} · {{ $p['producto'] }}</div>
    @endforeach
@endif

@if ($observacion)
    <div class="sep"></div>
    <div><span class="b">Obs.:</span> {{ $observacion }}</div>
@endif

<div class="sep"></div>
<div class="c b">{{ $pie }}</div>
@foreach ($extras as $linea)
    <div class="c muted">{{ $linea }}</div>
@endforeach
<div id="fin"></div>
</body>
</html>
