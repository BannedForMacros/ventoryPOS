@php
    $moneda = 'S/';
    $money = fn ($v) => $moneda . ' ' . number_format((float) $v, 2, '.', ',');
    $fmt = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $negocioNombre = $empresa?->nombre_comercial ?: $empresa?->razon_social ?: 'Mi Negocio';
    $anticipo = $entrega->anticipo;
    $local = $anticipo?->venta?->local;
    $direccion = $local?->direccion ?: $empresa?->direccion;
    $telefono  = $local?->telefono ?: $empresa?->telefono;
    $clienteNombre = trim((string) ($cliente?->nombre_completo ?? '')) ?: 'Cliente Varios';
    $clienteDoc = $cliente && $cliente->numero_documento
        ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
        : null;
    $items = $entrega->items;
    $totalValor = 0;
    foreach ($items as $ai) { $totalValor += (float) $ai->cantidad * (float) ($ai->item?->precio_unitario ?? 0); }
    if (count($items) === 0) { $totalValor = (float) $entrega->monto; }
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrega {{ $entrega->numero }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1e293b; background: #f1f5f9; font-size: 13px; line-height: 1.45; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 12px; background: #0f172a; }
        .toolbar button { font: inherit; font-weight: 600; cursor: pointer; border: 0; border-radius: 8px; padding: 9px 18px; color: #fff; background: #0d9488; }
        .toolbar button.sec { background: #334155; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 18mm 16mm; box-shadow: 0 6px 24px rgba(15,23,42,.15); }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid #0d9488; padding-bottom: 14px; }
        .head .brand { display: flex; gap: 14px; align-items: center; }
        .head .logo { height: 66px; width: auto; max-width: 180px; object-fit: contain; }
        .head .neg-nombre { font-size: 20px; font-weight: 800; color: #0f172a; }
        .head .neg-meta { color: #64748b; font-size: 12px; }
        .doc-box { text-align: right; }
        .doc-box .tit { font-size: 15px; font-weight: 800; color: #0d9488; letter-spacing: .5px; }
        .doc-box .num { font-size: 18px; font-weight: 800; }
        .doc-box .meta { color: #64748b; font-size: 12px; margin-top: 2px; }
        .parts { display: flex; justify-content: space-between; gap: 24px; margin: 18px 0; }
        .parts .label { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; font-weight: 700; }
        .parts .val { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead th { background: #0f172a; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; padding: 9px 10px; text-align: left; }
        thead th.r, tbody td.r { text-align: right; }
        thead th.c, tbody td.c { text-align: center; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .tots { margin-top: 14px; display: flex; justify-content: flex-end; }
        .tots table { width: 300px; }
        .tots td { padding: 5px 10px; border: 0; }
        .tots .k { color: #64748b; }
        .tots .v { text-align: right; font-weight: 600; }
        .tots .grand td { border-top: 2px solid #0f172a; padding-top: 9px; font-size: 16px; font-weight: 800; }
        .obs { margin-top: 18px; padding: 12px 14px; background: #f8fafc; border-left: 3px solid #0d9488; border-radius: 4px; }
        .obs .label { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; font-weight: 700; }
        .firma { margin-top: 46px; display: flex; justify-content: space-between; gap: 40px; }
        .firma .box { flex: 1; text-align: center; border-top: 1px solid #94a3b8; padding-top: 6px; color: #64748b; font-size: 12px; }
        .note { margin-top: 22px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
        <button class="sec" onclick="window.close()">Cerrar</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="brand">
                @if ($logoUrl)
                    <img class="logo" src="{{ $logoUrl }}" alt="Logo">
                @endif
                <div>
                    <div class="neg-nombre">{{ $negocioNombre }}</div>
                    @if ($empresa?->ruc)<div class="neg-meta">RUC {{ $empresa->ruc }}</div>@endif
                    @if ($direccion)<div class="neg-meta">{{ $direccion }}</div>@endif
                    @if ($telefono)<div class="neg-meta">Tel. {{ $telefono }}</div>@endif
                </div>
            </div>
            <div class="doc-box">
                <div class="tit">CONSTANCIA DE ENTREGA</div>
                <div class="num">N° {{ $entrega->numero }}</div>
                <div class="meta">Fecha: {{ $fmt($entrega->fecha) }}</div>
                @if ($anticipo?->venta?->numero)<div class="meta">Venta origen: {{ $anticipo->venta->numero }}</div>@endif
            </div>
        </div>

        <div class="parts">
            <div>
                <div class="label">Cliente</div>
                <div class="val">{{ $clienteNombre }}</div>
                @if ($clienteDoc)<div class="neg-meta">{{ $clienteDoc }}</div>@endif
                @if ($cliente?->telefono)<div class="neg-meta">Tel. {{ $cliente->telefono }}</div>@endif
            </div>
            <div style="text-align:right">
                <div class="label">Entregado por</div>
                <div class="val">{{ $entrega->user?->name ?? '—' }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="c" style="width:60px">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:80px">Unidad</th>
                    <th class="r" style="width:100px">Valor unit.</th>
                    <th class="r" style="width:110px">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $ai)
                    @php $pu = (float) ($ai->item?->precio_unitario ?? 0); @endphp
                    <tr>
                        <td class="c">{{ $qty($ai->cantidad) }}</td>
                        <td>{{ $ai->item?->producto_nombre ?? 'Producto' }}</td>
                        <td>{{ $ai->item?->unidad_nombre ?: 'und' }}</td>
                        <td class="r">{{ $money($pu) }}</td>
                        <td class="r">{{ $money((float) $ai->cantidad * $pu) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="c">{{ $qty($entrega->cantidad) }}</td>
                        <td>Entrega de anticipo{{ $anticipo?->producto_id && $anticipo->relationLoaded('producto') ? ' — ' . $anticipo->producto?->nombre : '' }}</td>
                        <td>—</td>
                        <td class="r">—</td>
                        <td class="r">{{ $money($entrega->monto) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="tots">
            <table>
                <tr class="grand"><td>VALOR ENTREGADO</td><td class="v">{{ $money($totalValor) }}</td></tr>
            </table>
        </div>

        @if ($entrega->observacion)
            <div class="obs">
                <div class="label">Observación</div>
                <div>{{ $entrega->observacion }}</div>
            </div>
        @endif

        <div class="firma">
            <div class="box">Entregó</div>
            <div class="box">Recibí conforme (cliente)</div>
        </div>

        <div class="note">
            Constancia de entrega de mercadería pagada por adelantado. No es comprobante de pago.
        </div>
    </div>
</body>
</html>
