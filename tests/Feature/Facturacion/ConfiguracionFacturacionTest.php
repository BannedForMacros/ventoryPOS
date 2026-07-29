<?php

use App\Services\Facturacion\FacturacionEmpresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * `GET /api/v1/configuracion` es la fuente ÚNICA de verdad (§7 del contrato).
 *
 * ─── Qué estaba mal ──────────────────────────────────────────────────────────
 *
 * `ConfiguracionFacturacion` existía pero NADIE la instanciaba: el endpoint no se
 * llamaba nunca. El POS seguía leyendo claves locales (`facturamac.series`,
 * `facturamac.umbral_boleta_identificada`) que ya no existen en el config
 * adelgazado, así que se quedaba con los defaults pasara lo que pasara:
 *
 *   · Umbral siempre 700. Con un tenant configurado a 500, el POS dejaba cerrar
 *     una boleta de 600 al Cliente General y el emisor la rechazaba DESPUÉS de
 *     cobrar — justo el "fallar tarde" que el umbral configurable evita.
 *   · `series` siempre `[]`, así que la caja nunca veía con qué serie se iba a
 *     emitir. Una boleta de prueba salió por la serie fiscal REAL `B001` y nadie
 *     lo notó hasta revisar la numeración.
 *   · El aviso de modo salía de `/ping` + `sunat_beta`, que no distingue
 *     `simulacion` de `produccion`.
 *
 * ─── Las dos garantías que hay que sostener a la vez ─────────────────────────
 *
 *   1. FAIL-SAFE EN EL AVISO: emisor caído ⇒ `esProduccion()` es TRUE.
 *   2. FAIL-OPEN EN LA CAJA: emisor caído ⇒ la venta se cierra igual.
 */

/** Respuesta §7 completa, con los valores del emisor distintos de los defaults. */
function cfgEmisorRemoto(array $override = []): array
{
    return array_merge([
        'empresa'                    => ['razon_social' => 'MACSOFT E.I.R.L.', 'ruc' => '20614911051'],
        'modo'                       => 'beta',
        'emision_activa'             => true,
        'envia_a_sunat'              => true,
        'tasa_impuesto'              => 18.0,
        'umbral_boleta_identificada' => 500.0,
        'tolerancia_redondeo'        => 0.05,
        'monedas'                    => ['PEN'],
        'series_por_defecto'         => ['factura' => 'F002', 'boleta' => 'B002'],
        'unidades'                   => ['UND' => 'NIU', 'KG' => 'KGM'],
    ], $override);
}

beforeEach(function () {
    config(['facturamac.base_url' => 'http://emisor.test']);

    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);

    // Empresa CONECTADA y emitiendo: hay a quién preguntar. Antes esto era
    // `config(['facturamac.enabled' => true])`, un flag del `.env` común a toda la
    // instalación; ahora la conexión —y el token— son de ESTA empresa.
    $this->env->conectarFacturacion();

    $this->actingAs($this->env->admin);
});

/** La configuración de LA empresa del entorno. Atajo para no repetirlo 12 veces. */
function cfg(): \App\Services\Facturacion\ConfiguracionFacturacion
{
    // Instancia NUEVA a propósito en cada llamada: varios tests comprueban que la
    // caché compartida (no la memoria por request) es la que evita las llamadas HTTP.
    return (new FacturacionEmpresa())->configuracion(test()->env->empresa->id);
}

// ── Caché ───────────────────────────────────────────────────────────────────

it('cachea la configuración: varias lecturas, una sola llamada al emisor', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response(cfgEmisorRemoto(), 200)]);

    // Instancias DISTINTAS a propósito: la memoria por request no basta, la caché
    // compartida es la que evita meter una llamada HTTP en cada carga de pantalla.
    expect(cfg()->umbralBoletaIdentificada())->toBe(500.0);
    expect(cfg()->modo())->toBe('beta');
    expect(cfg()->seriesPorDefecto()['boleta'])->toBe('B002');

    Http::assertSentCount(1);
});

it('olvidar() fuerza a volver a preguntar', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response(cfgEmisorRemoto(), 200)]);

    $config = cfg();
    $config->modo();
    $config->olvidar();
    cfg()->modo();

    Http::assertSentCount(2);
});

it('no vuelve a intentarlo en cada lectura mientras el emisor esté caído', function () {
    // Sin este cooldown, cada pantalla del POS se comería un timeout entero.
    // Se cuentan los INTENTOS a mano: una petición que lanza no queda registrada
    // en el historial de Http::fake, así que assertSentCount(0) no diría nada.
    $intentos = 0;
    Http::fake(function () use (&$intentos) {
        $intentos++;
        throw new ConnectionException('Connection refused');
    });

    cfg()->modo();
    cfg()->umbralBoletaIdentificada();
    cfg()->esProduccion();

    expect($intentos)->toBe(1);
});

// ── El emisor manda ─────────────────────────────────────────────────────────

it('el umbral del emisor manda sobre el default local de 700', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response(cfgEmisorRemoto(), 200)]);

    expect(cfg()->umbralBoletaIdentificada())->toBe(500.0);
});

it('una boleta de 600 al Cliente General se BLOQUEA si el emisor pide identificar desde 500', function () {
    // Con el umbral cableado a 700 esta venta se cerraba y el emisor la rechazaba
    // después de cobrar. Es el caso concreto que motivó conectar el endpoint.
    Http::fake(['*/api/v1/configuracion' => Http::response(cfgEmisorRemoto(), 200)]);

    $producto = $this->env->crearProducto(['precio_venta' => 600, 'precio_costo' => 400, 'stock_inicial' => 10]);
    $turno    = $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'boleta',
        'turno_id'         => $turno->id,
        'cliente_id'       => $this->env->clienteGeneral->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 600,
            'incluye_igv'        => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 600]],
    ])->assertSessionHasErrors('cliente_id');
});

it('esa misma boleta de 600 SÍ se cierra con el umbral por defecto de 700', function () {
    // Contraprueba: el bloqueo viene del EMISOR, no de una regla nueva del POS.
    Http::fake(['*/api/v1/configuracion' => Http::response(
        cfgEmisorRemoto(['umbral_boleta_identificada' => 700.0]), 200,
    )]);

    $producto = $this->env->crearProducto(['precio_venta' => 600, 'precio_costo' => 400, 'stock_inicial' => 10]);
    $turno    = $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'boleta',
        'turno_id'         => $turno->id,
        'cliente_id'       => $this->env->clienteGeneral->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 600,
            'incluye_igv'        => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 600]],
    ])->assertSessionHasNoErrors();
});

// ── Fail-safe / fail-open ───────────────────────────────────────────────────

it('con el emisor caído esProduccion() es TRUE y el modo es desconocido', function () {
    // Un aviso de más no cuesta nada; uno de menos cuesta una Nota de Crédito.
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $config = cfg();

    expect($config->modo())->toBe('desconocido');
    expect($config->esProduccion())->toBeTrue();
    // Y los defaults legales siguen ahí para que la pantalla pueda pintarse.
    expect($config->umbralBoletaIdentificada())->toBe(700.0);
    expect($config->tasaImpuesto())->toBe(18.0);
    expect($config->monedas())->toBe(['PEN']);
    expect($config->seriesPorDefecto())->toBe(['factura' => null, 'boleta' => null]);
});

it('con el emisor caído la venta NO se bloquea: la caja sigue cobrando', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $producto = $this->env->crearProducto(['precio_venta' => 50, 'precio_costo' => 30, 'stock_inicial' => 10]);
    $turno    = $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'boleta',
        'turno_id'         => $turno->id,
        'cliente_id'       => $this->env->clienteGeneral->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
            'incluye_igv'        => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ])->assertSessionHasNoErrors();
});

it('el POS recibe modo, serie y umbral del emisor, no del config local', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response(cfgEmisorRemoto(), 200)]);
    $this->env->abrirTurno($this->env->admin);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facturacion.enabled', true)
            // `beta` ya no se confunde con `produccion` ni con `simulacion`.
            ->where('facturacion.modo', 'beta')
            ->where('facturacion.produccion', false)
            ->where('facturacion.umbral_boleta_identificada', 500)
            // Serie visible en caja: es lo que habría delatado la boleta emitida
            // por la serie fiscal real B001 en un ensayo.
            ->where('facturacion.series.boleta', 'B002')
            ->where('facturacion.series.factura', 'F002'));
});

it('el POS avisa de PRODUCCIÓN cuando el emisor no contesta', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    $this->env->abrirTurno($this->env->admin);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facturacion.modo', 'desconocido')
            ->where('facturacion.produccion', true));
});

it('distingue simulacion de produccion', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response(
        cfgEmisorRemoto(['modo' => 'simulacion', 'envia_a_sunat' => false]), 200,
    )]);

    $config = cfg();

    expect($config->modo())->toBe('simulacion');
    expect($config->esProduccion())->toBeFalse();
    expect($config->enviaASunat())->toBeFalse();
    // Sigue emitiendo (numera y calcula), solo que no sale hacia SUNAT.
    expect($config->emisionActiva())->toBeTrue();
});
