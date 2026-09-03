<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    /* dompdf: solo CSS básico. Nada de flex/grid/color-mix. */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    .header { border-bottom: 3px solid #1e3a5f; padding-bottom: 8px; margin-bottom: 12px; }
    .header h1 { font-size: 17px; color: #1e3a5f; }
    .header .sub { font-size: 10px; color: #64748b; margin-top: 2px; }
    .periodo { font-size: 11px; font-weight: bold; color: #0f172a; }

    /* Caja protagonista: la utilidad */
    .utilidad-box { background: #f0fdf4; border: 2px solid #16a34a; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; }
    .utilidad-box.negativa { background: #fef2f2; border-color: #dc2626; }
    .utilidad-box .etiqueta { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: bold; }
    .utilidad-box .monto { font-size: 26px; font-weight: bold; color: #15803d; }
    .utilidad-box.negativa .monto { color: #b91c1c; }
    .utilidad-box .margen { font-size: 10px; color: #64748b; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #1e3a5f; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 7px; text-align: left; }
    td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .seccion { font-size: 12px; font-weight: bold; color: #1e3a5f; margin: 14px 0 6px; border-left: 4px solid #1e3a5f; padding-left: 7px; }
    .fila-fuerte td { background: #f1f5f9; font-weight: bold; }
    .positivo { color: #15803d; font-weight: bold; }
    .negativo { color: #b91c1c; }
    .muted { color: #64748b; }
    .resumen-chips td { background: #f8fafc; border: 1px solid #e2e8f0; }
    .chip-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: bold; }
    .chip-valor { font-size: 13px; font-weight: bold; color: #0f172a; }
    .chip-nota { font-size: 8px; color: #94a3b8; }
    .footer { margin-top: 16px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>

@php
    $fmt = fn ($n) => 'S/ ' . number_format((float) $n, 2, '.', ',');
    $fmtFecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
    $comprobanteLabel = [
        'ticket' => 'Ticket', 'boleta' => 'Boleta', 'factura' => 'Factura',
        'boleta_externa' => 'Boleta electrónica externa', 'factura_externa' => 'Factura electrónica externa',
    ];
    $estadoSunatLabel = [
        'aceptado' => 'Aceptados por SUNAT', 'enviando' => 'Enviando', 'enviado' => 'Enviados (por confirmar)',
        'pendiente_resumen' => 'Pendientes de resumen diario', 'en_resumen' => 'En resumen diario',
        'pendiente' => 'Pendientes de envío', 'error_envio' => 'Error de envío', 'error_mapeo' => 'Error de datos',
        'rechazado' => 'Rechazados por SUNAT', 'anulado' => 'Anulados (nota de crédito)',
        'no_emitido' => 'No emitidos', 'simulado' => 'Simulados (prueba)',
    ];
    $cobradoTotal = collect($por_metodo)->sum('total');
    $neta = $kpis['utilidad_neta'];
@endphp

{{-- Encabezado --}}
<div class="header">
    <table style="margin-bottom:0">
        <tr>
            <td style="border:none; padding:0">
                <h1>{{ $empresa->razon_social ?? $empresa->nombre_comercial ?? 'Mi empresa' }}</h1>
                <div class="sub">
                    @if($empresa->ruc) RUC {{ $empresa->ruc }} · @endif
                    Reporte de cierre de período
                </div>
            </td>
            <td style="border:none; padding:0; text-align:right">
                <div class="periodo">{{ $fmtFecha($filters['fecha_desde']) }} al {{ $fmtFecha($filters['fecha_hasta']) }}</div>
                <div class="sub">Generado el {{ $generado }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- LA UTILIDAD: lo primero que se ve --}}
<div class="utilidad-box {{ $neta < 0 ? 'negativa' : '' }}">
    <table style="margin-bottom:0">
        <tr>
            <td style="border:none; padding:0">
                <div class="etiqueta">Utilidad neta del período</div>
                <div class="monto">{{ $fmt($neta) }}</div>
                <div class="margen">
                    @if($kpis['margen_neto'] !== null) Margen neto: {{ $kpis['margen_neto'] }}% · @endif
                    Utilidad bruta: {{ $fmt($kpis['utilidad_bruta']) }}
                    @if($kpis['margen_bruto'] !== null) ({{ $kpis['margen_bruto'] }}%) @endif
                </div>
            </td>
            <td style="border:none; padding:0; text-align:right; vertical-align:top">
                <div class="etiqueta">Ventas del período</div>
                <div style="font-size:16px; font-weight:bold; color:#0f172a">{{ $fmt($kpis['ventas']) }}</div>
                <div class="margen">
                    {{ number_format($kpis['ventas_count']) }} ventas
                    @if($kpis['variacion_ventas'] !== null)
                        · {{ $kpis['variacion_ventas'] >= 0 ? '+' : '' }}{{ $kpis['variacion_ventas'] }}% vs período anterior
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- Estado de resultados --}}
<div class="seccion">Estado de resultados</div>
<table>
    <tbody>
        <tr>
            <td>Ventas (netas de vuelto) <span class="muted">· IGV incluido {{ $fmt($kpis['igv']) }}</span></td>
            <td class="num positivo">{{ $fmt($kpis['ventas']) }}</td>
        </tr>
        <tr>
            <td>Costo de lo vendido <span class="muted">· costo congelado al momento de cada venta</span></td>
            <td class="num negativo">− {{ $fmt($kpis['costo']) }}</td>
        </tr>
        <tr class="fila-fuerte">
            <td>Utilidad bruta @if($kpis['margen_bruto'] !== null)<span class="muted"> · margen {{ $kpis['margen_bruto'] }}%</span>@endif</td>
            <td class="num">{{ $fmt($kpis['utilidad_bruta']) }}</td>
        </tr>
        <tr>
            <td>Gastos operativos <span class="muted">· {{ number_format($kpis['gastos_count']) }} gastos</span></td>
            <td class="num negativo">− {{ $fmt($kpis['gastos']) }}</td>
        </tr>
        <tr>
            <td>Devoluciones <span class="muted">· {{ number_format($kpis['devoluciones_count']) }} en el período</span></td>
            <td class="num negativo">− {{ $fmt($kpis['devoluciones']) }}</td>
        </tr>
        <tr class="fila-fuerte">
            <td><strong>Utilidad neta del período</strong> @if($kpis['margen_neto'] !== null)<span class="muted"> · margen {{ $kpis['margen_neto'] }}%</span>@endif</td>
            <td class="num {{ $neta >= 0 ? 'positivo' : 'negativo' }}" style="font-size:12px"><strong>{{ $fmt($neta) }}</strong></td>
        </tr>
    </tbody>
</table>

{{-- Resumen rápido --}}
<div class="seccion">Resumen del cierre</div>
<table class="resumen-chips">
    <tr>
        <td width="25%">
            <div class="chip-label">Cobrado en el período</div>
            <div class="chip-valor">{{ $fmt($cobradoTotal) }}</div>
            <div class="chip-nota">ventas + abonos a créditos</div>
        </td>
        <td width="25%">
            <div class="chip-label">Por cobrar (créditos)</div>
            <div class="chip-valor">{{ $fmt($kpis['por_cobrar']) }}</div>
            <div class="chip-nota">{{ number_format($kpis['por_cobrar_count']) }} ventas con saldo al corte</div>
        </td>
        <td width="25%">
            <div class="chip-label">Compras del período</div>
            <div class="chip-valor">{{ $fmt($kpis['compras']) }}</div>
            <div class="chip-nota">por pagar {{ $fmt($kpis['compras_pendiente']) }}</div>
        </td>
        <td width="25%">
            <div class="chip-label">Ventas anuladas</div>
            <div class="chip-valor">{{ number_format($kpis['anuladas_count']) }}</div>
            <div class="chip-nota">{{ $fmt($kpis['anuladas_monto']) }} revertidos</div>
        </td>
    </tr>
</table>

{{-- Cobros por método de pago --}}
@if(count($por_metodo) > 0)
<div class="seccion">Cobrado por método de pago</div>
<table>
    <thead>
        <tr><th>Método</th><th class="num">Ventas</th><th class="num">Abonos crédito</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
        @foreach($por_metodo as $m)
        <tr>
            <td>{{ $m['nombre'] }}</td>
            <td class="num">{{ $fmt($m['ventas']) }}</td>
            <td class="num">{{ $m['abonos'] > 0 ? $fmt($m['abonos']) : '—' }}</td>
            <td class="num"><strong>{{ $fmt($m['total']) }}</strong></td>
        </tr>
        @endforeach
        <tr class="fila-fuerte">
            <td>Total cobrado</td>
            <td class="num" colspan="2"></td>
            <td class="num">{{ $fmt($cobradoTotal) }}</td>
        </tr>
    </tbody>
</table>
@endif

{{-- Comprobantes por tipo --}}
@if(count($por_comprobante) > 0)
<div class="seccion">Comprobantes emitidos</div>
<table>
    <thead>
        <tr><th>Tipo</th><th class="num">Emitidos</th><th class="num">Anulados</th><th>Numeración</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
        @foreach($por_comprobante as $c)
        <tr>
            <td>{{ $comprobanteLabel[$c['tipo']] ?? $c['tipo'] }}</td>
            <td class="num">{{ number_format($c['emitidos']) }}</td>
            <td class="num">{{ $c['anulados'] > 0 ? number_format($c['anulados']) : '—' }}</td>
            <td class="muted">
                @if($c['primer_numero'])
                    {{ $c['primer_numero'] === $c['ultimo_numero'] ? $c['primer_numero'] : $c['primer_numero'] . ' → ' . $c['ultimo_numero'] }}
                @else — @endif
            </td>
            <td class="num"><strong>{{ $fmt($c['total']) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Electrónicos SUNAT --}}
@if(count($electronicos) > 0)
<div class="seccion">Comprobantes electrónicos SUNAT</div>
<table>
    <thead>
        <tr><th>Estado</th><th class="num">Cantidad</th></tr>
    </thead>
    <tbody>
        @foreach($electronicos as $e)
        <tr>
            <td>{{ $estadoSunatLabel[$e['estado']] ?? $e['estado'] }}</td>
            <td class="num"><strong>{{ number_format($e['count']) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Gastos --}}
@if(count($gastos_por_tipo) > 0)
<div class="seccion">Gastos por tipo</div>
<table>
    <thead>
        <tr><th>Tipo</th><th class="num">Cant.</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
        @foreach($gastos_por_tipo as $g)
        <tr>
            <td>{{ $g['nombre'] }}</td>
            <td class="num">{{ number_format($g['count']) }}</td>
            <td class="num"><strong>{{ $fmt($g['total']) }}</strong></td>
        </tr>
        @endforeach
        <tr class="fila-fuerte">
            <td>Total gastos</td><td></td>
            <td class="num">{{ $fmt($kpis['gastos']) }}</td>
        </tr>
    </tbody>
</table>
@endif

{{-- Créditos --}}
<div class="seccion">Créditos (cuentas por cobrar)</div>
<table>
    <tbody>
        <tr>
            <td>Vendido a crédito en el período</td>
            <td class="num">{{ $fmt($kpis['credito_otorgado']) }} <span class="muted">({{ number_format($kpis['credito_count']) }} ventas)</span></td>
        </tr>
        <tr>
            <td>Cobrado en abonos durante el período</td>
            <td class="num positivo">{{ $fmt($kpis['credito_cobrado']) }}</td>
        </tr>
        <tr class="fila-fuerte">
            <td>Saldo total por cobrar al corte</td>
            <td class="num"><strong>{{ $fmt($kpis['por_cobrar']) }}</strong></td>
        </tr>
    </tbody>
</table>

@if(count($top_deudores) > 0)
<table>
    <thead>
        <tr><th>Mayores deudores al corte</th><th class="num">Ventas</th><th class="num">Saldo</th></tr>
    </thead>
    <tbody>
        @foreach($top_deudores as $d)
        <tr>
            <td>{{ $d['nombre'] }}</td>
            <td class="num">{{ number_format($d['ventas']) }}</td>
            <td class="num"><strong>{{ $fmt($d['saldo']) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Compras --}}
@if(count($compras_por_proveedor) > 0)
<div class="seccion">Compras por proveedor</div>
<table>
    <thead>
        <tr><th>Proveedor</th><th class="num">Compras</th><th class="num">Total</th><th class="num">Pagado</th><th class="num">Pendiente</th></tr>
    </thead>
    <tbody>
        @foreach($compras_por_proveedor as $p)
        <tr>
            <td>{{ $p['nombre'] }}</td>
            <td class="num">{{ number_format($p['count']) }}</td>
            <td class="num">{{ $fmt($p['total']) }}</td>
            <td class="num">{{ $fmt($p['pagado']) }}</td>
            <td class="num {{ $p['pendiente'] > 0 ? 'negativo' : 'muted' }}">{{ $p['pendiente'] > 0 ? $fmt($p['pendiente']) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">
    Reporte generado por VentoryPOS · {{ $generado }} · Período {{ $filters['fecha_desde'] }} al {{ $filters['fecha_hasta'] }}
</div>

</body>
</html>
