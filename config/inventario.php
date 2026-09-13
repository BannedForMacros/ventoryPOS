<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autocorrección del kardex
    |--------------------------------------------------------------------------
    |
    | Cuando un movimiento de stock llega con fecha anterior al último del
    | producto, o corrige uno ya registrado (edición, anulación, reverso), el
    | kardex en vivo queda desordenado. Con esta opción activa, el producto se
    | rearma solo desde sus documentos, en segundo plano (ver KardexService).
    |
    | Los tests la apagan (phpunit.xml): TestEnv crea stock inicial sin
    | documento que lo respalde, y rearmar esos productos contradiría el
    | escenario del test. Los tests del motor la encienden explícitamente.
    |
    */

    'autocorreccion_kardex' => (bool) env('INVENTARIO_AUTOCORRECCION', true),

];
