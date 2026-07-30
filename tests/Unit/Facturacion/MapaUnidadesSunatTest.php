<?php

use App\Services\Facturacion\MapaUnidadesSunat;

// `MapaUnidadesSunat::cargar()` consulta `unidad_sunat_map` (dentro de un try, para
// funcionar sin la tabla), así que necesita la aplicación levantada. Mismo patrón
// que VentaAContratoTest.
uses(Tests\TestCase::class);

/**
 * Traducción de las unidades del POS al Catálogo 03 de SUNAT.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * La semilla no contenía `SRV`, que es precisamente la abreviatura que
 * `ProductoController::crearPresentacionDefaultServicio()` crea SOLA para cada
 * servicio dado de alta desde la interfaz (`UnidadMedida::firstOrCreate(['nombre'
 * => 'Servicio'], ['abreviatura' => 'srv'])`). Resultado: TODO servicio del
 * sistema se declaraba como `NIU` ("unidad") en vez de `ZZ` ("servicio").
 *
 * No rompe la emisión —SUNAT acepta NIU— así que no habría saltado ninguna
 * alarma: simplemente todos los comprobantes de una empresa que vive de vender
 * servicios describían mal la operación, en silencio y para siempre.
 *
 * Tampoco había ninguna unidad de TIEMPO, y las licencias, suscripciones,
 * soporte y capacitación se facturan por mes, hora, día o año. Una licencia
 * anual declarada como "unidad" pierde justo el dato que la define.
 *
 * ─── Por qué en la semilla y no en `unidad_sunat_map` ────────────────────────
 *
 * La tabla es para las abreviaturas que se inventa cada empresa. Estas son
 * castellano corriente y una de ellas la genera el propio código: arreglarlas
 * por empresa obligaría a repetir la misma fila en cada instalación, y a que
 * alguien se acordara de hacerlo.
 */
it('traduce la unidad que el propio sistema crea para los servicios', function () {
    $mapa = app(MapaUnidadesSunat::class);

    // Tal cual la escribe crearPresentacionDefaultServicio(), en minúsculas.
    expect($mapa->codigoSunat(1, null, 'srv'))->toBe('ZZ')
        ->and($mapa->codigoSunat(1, null, 'SERV'))->toBe('ZZ')
        ->and($mapa->codigoSunat(1, null, 'servicio'))->toBe('ZZ');
});

it('traduce las unidades de tiempo de licencias y servicios', function () {
    $mapa = app(MapaUnidadesSunat::class);

    expect($mapa->codigoSunat(1, null, 'MES'))->toBe('MON')
        ->and($mapa->codigoSunat(1, null, 'mensual'))->toBe('MON')
        ->and($mapa->codigoSunat(1, null, 'HORA'))->toBe('HUR')
        ->and($mapa->codigoSunat(1, null, 'hr'))->toBe('HUR')
        ->and($mapa->codigoSunat(1, null, 'DIA'))->toBe('DAY')
        ->and($mapa->codigoSunat(1, null, 'anual'))->toBe('ANN')
        ->and($mapa->codigoSunat(1, null, 'año'))->toBe('ANN');
});

it('una unidad desconocida cae en NIU en vez de hacer fallar la emisión', function () {
    // Es la decisión de diseño: perder precisión en la unidad es un problema
    // menor; que una venta no se pueda facturar es un problema de caja. La
    // abreviatura original se conserva entre paréntesis en la descripción del
    // ítem, así que el comprobante sigue siendo legible para el cliente.
    $mapa = app(MapaUnidadesSunat::class);

    expect($mapa->codigoSunat(1, null, 'latas'))->toBe('NIU')
        ->and($mapa->codigoSunat(1, null, 'lo-que-sea'))->toBe('NIU')
        ->and($mapa->codigoSunat(1, null, ''))->toBe('NIU')
        ->and($mapa->codigoSunat(1, null, null))->toBe('NIU');
});

it('las unidades de mercadería que ya funcionaban siguen igual', function () {
    $mapa = app(MapaUnidadesSunat::class);

    expect($mapa->codigoSunat(1, null, 'UND'))->toBe('NIU')
        ->and($mapa->codigoSunat(1, null, 'KG'))->toBe('KGM')
        ->and($mapa->codigoSunat(1, null, 'm3'))->toBe('MTQ')
        ->and($mapa->codigoSunat(1, null, 'BOL'))->toBe('BG')
        ->and($mapa->codigoSunat(1, null, 'CAJA'))->toBe('BX');
});
