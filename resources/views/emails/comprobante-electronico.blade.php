{{--
    Correo del comprobante electrónico para el cliente.

    HTML plano y a la antigua (tablas, estilos en línea, sin webfonts ni imágenes
    externas) porque los clientes de correo —Outlook sobre todo— ignoran <style>,
    flexbox y grid. Lo que aquí se ve bonito con CSS moderno, allí se ve roto.

    Todo el contenido importante está también en texto: si las imágenes o los
    estilos se bloquean, el cliente sigue viendo su número de comprobante, el
    total y el enlace.
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $etiqueta }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif; color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(15,23,42,.08);">

                {{-- Cabecera --}}
                <tr>
                    <td style="background:#0f172a; padding:20px 28px;">
                        <div style="color:#ffffff; font-size:18px; font-weight:700;">{{ $negocio }}</div>
                        @if ($empresa?->ruc)
                            <div style="color:#94a3b8; font-size:12px; margin-top:2px;">RUC {{ $empresa->ruc }}</div>
                        @endif
                    </td>
                </tr>

                {{-- Cuerpo --}}
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 14px; font-size:15px;">
                            Hola {{ $clienteNombre }},
                        </p>
                        <p style="margin:0 0 20px; font-size:15px; line-height:1.5;">
                            Gracias por tu compra. Aquí tienes tu comprobante electrónico.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:22px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    <div style="font-size:11px; letter-spacing:.6px; text-transform:uppercase; color:#94a3b8; font-weight:700;">Comprobante</div>
                                    <div style="font-size:17px; font-weight:800; color:#0f172a; margin-top:2px;">{{ $etiqueta }}</div>
                                    @if ($fecha)
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">Fecha: {{ $fecha }}</div>
                                    @endif
                                    <div style="font-size:15px; font-weight:700; color:#0f172a; margin-top:10px;">Total: S/ {{ $total }}</div>
                                </td>
                            </tr>
                        </table>

                        {{-- Botón: <a> con padding, no <button>. Ningún cliente de correo ejecuta JS. --}}
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                            <tr>
                                <td style="background:#2563eb; border-radius:8px;">
                                    <a href="{{ $enlace }}"
                                       style="display:inline-block; padding:12px 24px; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none;">
                                        Descargar PDF
                                    </a>
                                </td>
                            </tr>
                        </table>

                        {{-- El enlace también en texto: hay clientes que no muestran el botón. --}}
                        <p style="margin:0 0 6px; font-size:12px; color:#64748b;">
                            Si el botón no funciona, copia y pega este enlace:
                        </p>
                        <p style="margin:0 0 20px; font-size:12px; word-break:break-all;">
                            <a href="{{ $enlace }}" style="color:#2563eb;">{{ $enlace }}</a>
                        </p>

                        @if ($tieneAdjunto)
                            <p style="margin:0 0 8px; font-size:13px; color:#475569;">
                                También encontrarás el PDF adjunto a este correo.
                            </p>
                        @endif

                        <p style="margin:0; font-size:12px; color:#94a3b8;">
                            El enlace estará disponible {{ $dias }} días. Pasado ese plazo, pídenos el comprobante de nuevo y te lo reenviamos.
                        </p>
                    </td>
                </tr>

                {{-- Pie --}}
                <tr>
                    <td style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 28px;">
                        <div style="font-size:12px; color:#64748b;">
                            {{ $negocio }}@if ($empresa?->direccion) · {{ $empresa->direccion }}@endif
                        </div>
                        @if ($empresa?->telefono)
                            <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Tel. {{ $empresa->telefono }}</div>
                        @endif
                        <div style="font-size:11px; color:#cbd5e1; margin-top:8px;">
                            Correo automático: por favor, no respondas a este mensaje.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
