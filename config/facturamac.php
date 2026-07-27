<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interruptor general
    |--------------------------------------------------------------------------
    |
    | Por defecto FALSE. La emisión electrónica es irreversible ante SUNAT: un
    | comprobante mal emitido solo se corrige con Nota de Crédito. Preferimos que
    | cada instalación lo encienda a conciencia antes que arrastrar un default
    | peligroso al desplegar. Con `enabled=false` el POS sigue funcionando
    | exactamente como hoy (ticket interno, cero llamadas de red).
    |
    */
    'enabled' => env('FACTURAMAC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Conexión con FacturaMac
    |--------------------------------------------------------------------------
    |
    | `token` es un personal access token de Sanctum ligado a un usuario de
    | FacturaMac que pertenece al Tenant correcto: el tenant (y por tanto el RUC
    | emisor, el certificado y la clave SOL) se deduce del token, nunca se manda
    | en el payload.
    |
    | `timeout` corto a propósito: el cajero no espera a SUNAT. FacturaMac responde
    | 201 en cuanto persiste el borrador y encola; si tarda más de 20 s es que algo
    | va mal y conviene fallar rápido y reintentar en el Job.
    |
    */
    'base_url' => env('FACTURAMAC_URL', 'http://localhost:8001'),
    'token'    => env('FACTURAMAC_TOKEN'),
    'timeout'  => env('FACTURAMAC_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Series por tipo de comprobante
    |--------------------------------------------------------------------------
    |
    | Series DEDICADAS al POS (F002 / B002) en vez de reutilizar las del módulo web
    | de FacturaMac (F001 / B001): así se puede auditar de un vistazo qué salió de
    | caja y qué se emitió a mano, y un problema en la integración no ensucia la
    | numeración del flujo manual.
    |
    */
    'series' => [
        '01' => env('FACTURAMAC_SERIE_FACTURA', 'F002'), // factura
        '03' => env('FACTURAMAC_SERIE_BOLETA',  'B002'), // boleta
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardas del mapper (§5 del plan de integración)
    |--------------------------------------------------------------------------
    |
    | `tasa_igv_soportada`: FacturaMac tiene el 18 % CABLEADO en tres sitios
    | (config/sunat.php, DetalleComprobante y Producto::getPrecioConIgvAttribute).
    | ventoryPOS, en cambio, lee `empresas.tasa_igv`, que es configurable. Si una
    | empresa tuviera otra tasa, el comprobante saldría descuadrado EN SILENCIO.
    | Por eso el mapper aborta en vez de emitir (G4).
    |
    | `tolerancia_centavos`: ventoryPOS redondea el IGV una sola vez a nivel de
    | venta; SUNAT lo suma por ítem. Los dos criterios difieren en 1–3 céntimos con
    | frecuencia. Hasta 0.05 la diferencia se absorbe en la línea gravada mayor
    | (§5.3); por encima es un bug de mapeo y NO se emite. El umbral es estrecho a
    | propósito: un comprobante no emitido y visible se arregla; uno emitido con el
    | importe equivocado exige Nota de Crédito ante SUNAT.
    |
    | `umbral_boleta_identificada`: SUNAT exige identificar al adquirente en boletas
    | desde S/ 700. Por debajo se admite el "Cliente General" (tipo doc '0').
    |
    */
    'tasa_igv_soportada'         => 18.0,
    'tolerancia_centavos'        => 0.05,
    'umbral_boleta_identificada' => 700.0,

];
