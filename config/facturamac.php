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
    | Aquí NO están ni el interruptor ni el token. Y es a propósito.
    |--------------------------------------------------------------------------
    |
    | Hasta 2026-07 este archivo tenía `enabled` (FACTURAMAC_ENABLED) y `token`
    | (FACTURAMAC_TOKEN). Los dos se han ido a la base de datos, a la tabla
    | `facturacion_conexiones`, UNA FILA POR EMPRESA.
    |
    | POR QUÉ: un token de FacturaMac identifica a UNA empresa emisora — de él se
    | deducen el RUC, el certificado y la clave SOL. El `.env` es uno solo para
    | toda la instalación, y esta instalación sirve a dos contribuyentes
    | distintos (MacSoft E.I.R.L. y HYC FERROMATERIALES SRL). Con un único token,
    | cuando vendía la segunda su comprobante salía firmado con el RUC de la
    | primera: facturar a nombre de otro contribuyente, sobre documentos fiscales
    | irreversibles. No era arreglable aquí, porque no hay dónde poner el segundo
    | token.
    |
    | Dónde vive ahora cada cosa:
    |
    |   token, RUC del emisor, modo, ¿emite? → `facturacion_conexiones`, por
    |     empresa. Se gestiona ENTERAMENTE por interfaz, en Configuración →
    |     Facturación Electrónica, pegando el código corto de vinculación que da
    |     FacturaMac. Nadie tiene que entrar por SSH ni editar este archivo.
    |
    |   ¿esta empresa emite? → `FacturacionEmpresa::activa($empresaId)`. Es el
    |     reemplazo literal de `config('facturamac.enabled')` y el ÚNICO sitio
    |     desde el que se pregunta.
    |
    | Si FACTURAMAC_ENABLED o FACTURAMAC_TOKEN siguen en algún `.env`, ya no se
    | leen. Pueden borrarse cuando se quiera.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Dónde vive FacturaMac
    |--------------------------------------------------------------------------
    |
    | Esto SÍ se queda aquí, y no en la tabla por empresa: FacturaMac es un único
    | servicio para toda la instalación. `base_url` responde a "a qué máquina se
    | llama", que es infraestructura de despliegue, no una decisión del usuario.
    | De hecho ponerlo por empresa sería peligroso: dejaría que alguien apuntara
    | una empresa a un emisor cualquiera desde una pantalla del POS.
    |
    | `timeout` corto a propósito: el cajero no espera a SUNAT. FacturaMac
    | responde en cuanto persiste el comprobante y encola el envío; si tarda más
    | de 20 s es que algo va mal y conviene fallar rápido y reintentar en el Job.
    |
    */
    'base_url' => env('FACTURAMAC_URL', 'http://localhost:8001'),
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
    | como mucho ese rato en verse en caja. Bajarlo mucho castiga la carga del
    | POS; subirlo mucho retrasa un aviso que importa. Para forzarlo al instante
    | está `ConfiguracionFacturacion::olvidar()`.
    |
    | Consecuencia deliberada de este TTL: el POS NO manda la serie al emitir.
    | Mandar un valor cacheado 10 minutos significaría que, justo mientras alguien
    | cambia de serie para separar pruebas de producción, el POS seguiría forzando
    | la vieja. La serie se lee solo para MOSTRARLA en caja; quien numera es el
    | emisor. Ver ConfiguracionFacturacion::seriesPorDefecto().
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
