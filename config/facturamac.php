<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qué se configura AQUÍ y qué se configura en FacturaMac
    |--------------------------------------------------------------------------
    |
    | Este archivo describe UNA SOLA COSA: cómo llega ventoryPOS hasta FacturaMac.
    | Nada más. Todo lo fiscal —series, tasa de IGV, umbral de boleta identificada,
    | catálogo de unidades SUNAT, tipos de documento, si se emite y si se envía a
    | SUNAT de verdad— se configura EN FACTURAMAC y ventoryPOS lo CONSUME por
    | `GET /api/v1/configuracion` (ver App\Services\Facturacion\ConfiguracionFacturacion).
    |
    | POR QUÉ: mientras esos valores vivieron duplicados en los dos sistemas, cada
    | cambio había que hacerlo dos veces y la primera vez que alguien se olvidara
    | de una, el POS habría emitido con una serie o un umbral distintos de los que
    | cree el emisor — en silencio y sobre documentos fiscales irreversibles. La
    | configuración fiscal es competencia exclusiva de quien firma el comprobante.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | ¿Está CABLEADA la integración?
    |--------------------------------------------------------------------------
    |
    | Ojo con la distinción, que es la clave de todo el diseño:
    |
    |   `enabled` (aquí)   = "¿esta instalación de ventoryPOS sabe hablar con
    |                        FacturaMac?" Es infraestructura: hay URL y token, la
    |                        red llega, el módulo está desplegado. Es un
    |                        interruptor de DESPLIEGUE.
    |
    |   `emision_activa` /
    |   `modo` (FacturaMac)= "¿esta empresa emite a SUNAT y en qué modo?" Es una
    |                        decisión de NEGOCIO del emisor, y por tanto vive
    |                        junto al certificado y la clave SOL, no aquí.
    |
    | Con `enabled=false` el POS funciona exactamente como antes de existir la
    | integración: ticket interno, cero llamadas de red, cero validaciones SUNAT.
    | Por defecto FALSE: emitir mal es irreversible, así que cada instalación lo
    | enciende a conciencia en vez de arrastrar un default peligroso al desplegar.
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
    | emisor, el certificado, la clave SOL y TODA la configuración fiscal) se
    | deduce del token, nunca se manda en el payload. Un token equivocado emite
    | con el RUC equivocado: es dato sensible de despliegue.
    |
    | `timeout` corto a propósito: el cajero no espera a SUNAT. FacturaMac
    | responde en cuanto persiste el comprobante y encola el envío; si tarda más
    | de 20 s es que algo va mal y conviene fallar rápido y reintentar en el Job.
    |
    */
    'base_url' => env('FACTURAMAC_URL', 'http://localhost:8001'),
    'token'    => env('FACTURAMAC_TOKEN'),
    'timeout'  => env('FACTURAMAC_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Caché de la configuración remota
    |--------------------------------------------------------------------------
    |
    | Segundos que se guarda la respuesta de `GET /api/v1/configuracion`. La lee
    | el POS en CADA carga de pantalla, así que sin caché meteríamos una llamada
    | HTTP en el camino más caliente del sistema.
    |
    | 10 minutos es el equilibrio: un cambio de modo (beta → producción) tarda
    | como mucho ese rato en verse en caja, y quien lo haga puede forzarlo al
    | instante con `php artisan facturacion:estado --refrescar`. Bajarlo mucho
    | castiga la carga del POS; subirlo mucho retrasa un aviso que importa.
    |
    | `cooldown_fallo`: cuando FacturaMac no responde NO cacheamos la respuesta
    | (no existe), pero sí anotamos el fallo unos segundos para no reintentar la
    | conexión en cada request mientras esté caído. La caja debe seguir cobrando
    | aunque el emisor esté apagado, y esperar 3 s de timeout por pantalla no es
    | seguir cobrando.
    |
    */
    'config_ttl'     => env('FACTURAMAC_CONFIG_TTL', 600),
    'cooldown_fallo' => env('FACTURAMAC_CONFIG_COOLDOWN', 30),

];
