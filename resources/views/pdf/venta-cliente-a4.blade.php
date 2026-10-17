@php
    $m = fn ($v) => 'S/ ' . number_format((float) $v, 2, '.', ',');
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $doc_tipo }} {{ $doc_numero }}</title>
<style>
    body, div, table, td, th, img { margin: 0; padding: 0; box-sizing: border-box; }
    @page { margin: 30px 34px 46px 34px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.4px; color: #1f2937; line-height: 1.4; }
    table { width: 100%; border-collapse: collapse; }
    .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; }
    .muted { color: #6b7280; }

    .franja { height: 5px; background: #0F4C81; border-radius: 3px; margin-bottom: 16px; }
    .logo { max-height: 70px; max-width: 150px; }
    .negocio { font-size: 17px; font-weight: bold; color: #0F4C81; }
    .meta { color: #6b7280; font-size: 8.6px; line-height: 1.5; }

    .doc { border: 1.5px solid #0F4C81; border-radius: 8px; text-align: center; overflow: hidden; }
    .doc .ruc { padding: 7px 8px 3px; font-size: 9.5px; font-weight: bold; color: #0F4C81; }
    .doc .tipo { background: #0F4C81; color: #fff; padding: 6px 8px; font-size: 11px; font-weight: bold; letter-spacing: .8px; }
    .doc .num { padding: 6px 8px 8px; font-size: 14px; font-weight: bold; color: #111827; }

    .datos { margin-top: 16px; border: 1px solid #e5e7eb; border-radius: 8px; }
    .datos td { padding: 7px 11px; vertical-align: top; }
    .lbl { font-size: 7.2px; text-transform: uppercase; letter-spacing: .9px; color: #9ca3af; font-weight: bold; }
    .val { font-size: 9.6px; color: #111827; margin-top: 1px; }

    .items { margin-top: 16px; }
    .items th { background: #f3f6fa; color: #0F4C81; font-size: 7.6px; text-transform: uppercase; letter-spacing: .6px;
                padding: 7px 8px; text-align: left; border-bottom: 1.5px solid #0F4C81; }
    .items th.r { text-align: right; } .items th.c { text-align: center; }
    .items td { padding: 7px 8px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
    .prod { font-weight: bold; color: #111827; }
    .det { font-size: 8.2px; color: #6b7280; margin-top: 1px; }
    .desc { color: #b91c1c; font-size: 8px; }

    .resumen { margin-top: 12px; }
    .resumen > tbody > tr > td { vertical-align: top; }
    .letras { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; font-size: 8.6px; }
    .tot td { padding: 3px 0; font-size: 9.4px; }
    .tot .k { color: #6b7280; }
    .tot .v { text-align: right; font-weight: bold; }
    .total { margin-top: 6px; background: #0F4C81; color: #fff; border-radius: 6px; }
    .total td { padding: 8px 11px; font-size: 13px; font-weight: bold; }

    .caja { margin-top: 12px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; }
    .chip { display: inline-block; background: #eef4fb; color: #0F4C81; border-radius: 10px; padding: 2px 8px;
            margin: 2px 4px 0 0; font-size: 8.6px; font-weight: bold; }
    .credito { border-color: #f59e0b; background: #fffbeb; }
    .anulada { position: fixed; top: 40%; left: 0; width: 100%; text-align: center; font-size: 90px; font-weight: bold;
               color: rgba(220, 38, 38, .12); transform: rotate(-24deg); }

    .gracias { margin-top: 22px; text-align: center; color: #0F4C81; font-size: 11px; font-weight: bold; }
    .pie { position: fixed; bottom: -22px; left: 0; width: 100%; text-align: center; font-size: 7.6px; color: #9ca3af;
           border-top: 1px solid #e5e7eb; padding-top: 5px; }
</style>
</head>
<body>
@if ($anulada)<div class="anulada">ANULADA</div>@endif

<div class="franja"></div>

<table>
    <tr>
        @if ($logo)
            <td style="width: 160px; vertical-align: middle;"><img class="logo" src="{{ $logo }}" alt=""></td>
        @endif
        <td style="vertical-align: middle; padding-right: 14px;">
            <div class="negocio">{{ $negocio }}</div>
            <div class="meta">
                @if ($razon && $razon !== $negocio){{ $razon }}<br>@endif
                @if ($direccion){{ $direccion }}<br>@endif
                @if ($telefono)Tel. {{ $telefono }}@endif
                @if ($telefono && $email) · @endif
                @if ($email){{ $email }}@endif
            </div>
        </td>
        <td style="width: 200px; vertical-align: middle;">
            <div class="doc">
                @if ($ruc)<div class="ruc">R.U.C. {{ $ruc }}</div>@endif
                <div class="tipo">{{ $doc_tipo }}</div>
                <div class="num">{{ $doc_numero }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td style="width: 46%;">
            <div class="lbl">Cliente</div>
            <div class="val b">{{ $cliente['nombre'] ?? 'Clientes varios' }}</div>
            @if (! empty($cliente['documento']))<div class="det">{{ $cliente['documento'] }}</div>@endif
            @if (! empty($cliente['direccion']))<div class="det">{{ $cliente['direccion'] }}</div>@endif
        </td>
        <td style="width: 18%;">
            <div class="lbl">Fecha</div>
            <div class="val">{{ $fecha }}</div>
            <div class="det">{{ $hora }}</div>
        </td>
        <td style="width: 18%;">
            <div class="lbl">Moneda</div>
            <div class="val">Soles (PEN)</div>
        </td>
        <td style="width: 18%;">
            @if ($atendido)
                <div class="lbl">Atendido por</div>
                <div class="val">{{ $atendido }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="c" style="width: 24px;">#</th>
            <th>Descripción</th>
            <th class="c" style="width: 70px;">Cant.</th>
            <th class="r" style="width: 78px;">P. unit.</th>
            <th class="r" style="width: 84px;">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $i => $it)
            <tr>
                <td class="c muted">{{ $i + 1 }}</td>
                <td>
                    <div class="prod">{{ $it['producto'] }}</div>
                    @if ($it['descripcion'])<div class="det">{{ $it['descripcion'] }}</div>@endif
                    @if ($it['descuento'] > 0)<div class="desc">Descuento -{{ $m($it['descuento']) }}</div>@endif
                </td>
                <td class="c">{{ $q($it['cantidad']) }}<div class="det">{{ $it['unidad'] }}</div></td>
                <td class="r">{{ $m($it['precio']) }}</td>
                <td class="r b">{{ $m($it['importe']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="resumen">
    <tr>
        <td style="padding-right: 22px;">
            <div class="letras">{{ $en_letras }}</div>

            @if (count($pagos) || $vuelto > 0)
                <div class="caja">
                    <div class="lbl" style="margin-bottom: 3px;">Forma de pago</div>
                    @foreach ($pagos as $p)
                        <span class="chip">{{ $p['metodo'] }} · {{ $m($p['monto']) }}</span>
                    @endforeach
                    @if ($vuelto > 0)<div class="det" style="margin-top: 4px;">Vuelto: {{ $m($vuelto) }}</div>@endif
                </div>
            @endif

            @if ($credito)
                <div class="caja credito">
                    <span class="b">Venta al crédito</span> · Pagado {{ $m($credito['pagado']) }}
                    · <span class="b" style="color: #b45309;">Saldo {{ $m($credito['saldo']) }}</span>
                    @if ($credito['vence']) · Vence {{ $credito['vence'] }}@endif
                </div>
            @endif
        </td>
        <td style="width: 230px;">
            <table class="tot">
                @if ($mostrar_igv)
                    <tr><td class="k">Op. gravada</td><td class="v">{{ $m($gravada) }}</td></tr>
                    <tr><td class="k">IGV ({{ $tasa_igv }}%)</td><td class="v">{{ $m($igv) }}</td></tr>
                @endif
                @if ($descuento > 0)
                    <tr><td class="k">Descuento</td><td class="v" style="color: #b91c1c;">-{{ $m($descuento) }}</td></tr>
                @endif
            </table>
            <table class="total">
                <tr><td>TOTAL</td><td class="r">{{ $m($total) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if (count($pendientes))
    <div class="caja">
        <div class="lbl" style="margin-bottom: 3px;">Pendiente por entregar (ya pagado)</div>
        @foreach ($pendientes as $p)
            <div>{{ $q($p['pendiente']) }} {{ $p['unidad'] }} · {{ $p['producto'] }}</div>
        @endforeach
    </div>
@endif

@if ($observacion)
    <div class="caja"><div class="lbl" style="margin-bottom: 2px;">Observación</div>{{ $observacion }}</div>
@endif

<div class="gracias">{{ $pie }}</div>
@foreach ($extras as $linea)
    <div class="c muted" style="font-size: 8.4px;">{{ $linea }}</div>
@endforeach

<div class="pie">{{ $negocio }} · {{ $doc_tipo }} {{ $doc_numero }} · {{ $fecha }} {{ $hora }}</div>
</body>
</html>
