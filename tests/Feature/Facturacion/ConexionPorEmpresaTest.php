<?php

use App\Jobs\ConsultarEstadoComprobante;
use App\Jobs\EmitirComprobanteElectronico;
use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\ConexionFacturacion;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\DevolucionService;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\VentaAContrato;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestEnv;

/**
 * La conexión con el emisor es POR EMPRESA, no del `.env`.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * La conexión con FacturaMac vivía en el `.env` de la instalación:
 * FACTURAMAC_ENABLED, FACTURAMAC_URL y FACTURAMAC_TOKEN. Un token de FacturaMac
 * identifica a UNA empresa emisora: de él se deducen el RUC, el certificado y la
 * clave SOL, y nada de eso viaja en el payload.
 *
 * Pero esta instalación sirve a DOS contribuyentes:
 *
 *     #1     20612345678  MacSoft E.I.R.L.
 *     #1097  20600134648  HYC FERROMATERIALES SRL
 *
 * Con un único token en el `.env`, cuando vendía HYC su boleta salía firmada con
 * el RUC de MacSoft. Eso es facturar a nombre de otro contribuyente, sobre
 * documentos fiscales irreversibles: para deshacerlo hace falta una Nota de
 * Crédito, y mientras tanto la declaración de los dos queda descuadrada.
 *
 * No tenía arreglo en el `.env` porque no hay dónde poner el segundo token. De ahí
 * `facturacion_conexiones`: una fila por empresa, con su token cifrado, y todo
 * gestionado por interfaz.
 *
 * ─── Qué defiende cada bloque ────────────────────────────────────────────────
 *
 *  1. Aislamiento — dos empresas, dos emisores: cada venta sale con SU token.
 *     Este es el test que justifica todo el trabajo.
 *  2. Guarda del RUC — un código de otro contribuyente no se guarda. Punto.
 *  3. Guarda del modo — en beta o simulación se conecta, pero NO se emite.
 *  4. Los Jobs — corren sin sesión y aun así resuelven la empresa correcta.
 *  5. Sin la tabla — el POS funciona como con el módulo apagado, sin 500.
 */

beforeEach(function () {
    config(['facturamac.base_url' => 'http://emisor.test']);

    // El polling se encola solo; en tests la cola es `sync` y su GET al emisor se
    // colaría en las aserciones sobre qué token se usó.
    Queue::fake();
});

/** Venta cerrada de 75 soles como boleta, lista para emitirse. */
function ventaEmitible(TestEnv $env, $turno): Venta
{
    $producto = $env->crearProducto(['precio_venta' => 75, 'incluye_igv' => true]);

    return app(VentaService::class)->crear([
        'tipo_comprobante' => 'boleta',
        'cliente_id'       => $env->clienteGeneral->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 75,
        ]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 75]],
    ], $env->admin, $turno);
}

/** Respuesta del emisor en la forma que espera `Respuesta::desdeArray()`. */
function respuestaEmisor(int $id = 4321): array
{
    return [
        'emitido'     => true,
        'modo'        => 'produccion',
        'id'          => $id,
        'estado'      => 'aceptado',
        'comprobante' => [
            'tipo'            => 'boleta',
            'serie'           => 'B002',
            'numero'          => 9,
            'numero_completo' => 'B002-00000009',
            'hash'            => 'abc',
            'qr'              => 'qr',
        ],
        'totales' => ['total' => 75.00],
    ];
}

/** Corre el job de emisión igual que el worker: sin sesión y sin cola. */
function emitir(Venta $venta): void
{
    (new EmitirComprobanteElectronico($venta))->handle(
        app(VentaAContrato::class),
        app(FacturacionEmpresa::class),
    );
}

/**
 * Tokens Bearer con los que se llamó al emisor, en orden.
 *
 * @return list<string>
 */
function tokensEnviados(): array
{
    return collect(Http::recorded())
        ->map(fn ($par) => (string) $par[0]->header('Authorization')[0] ?? '')
        ->map(fn ($h) => str_replace('Bearer ', '', $h))
        ->values()
        ->all();
}

// ══ 1 · EL FALLO ORIGINAL ═══════════════════════════════════════════════════

it('dos empresas con emisores distintos: cada venta se emite con el token de SU empresa', function () {
    // Este es EL test. Con la configuración en el `.env` era imposible de escribir:
    // no había forma de que dos empresas tuvieran dos tokens.
    Http::fake(['*/api/v1/ventas' => Http::response(respuestaEmisor(), 200)]);

    $macsoft = TestEnv::crear();
    $macsoft->conectarFacturacion(['token' => 'token-MACSOFT']);

    $hyc = TestEnv::crear();
    $hyc->conectarFacturacion(['token' => 'token-HYC']);

    $ventaMacsoft = ventaEmitible($macsoft, $macsoft->abrirTurno());
    $ventaHyc     = ventaEmitible($hyc, $hyc->abrirTurno());

    emitir($ventaMacsoft);
    emitir($ventaHyc);

    expect(tokensEnviados())->toBe(['token-MACSOFT', 'token-HYC'],
        'Cada venta debe salir con el token de SU empresa. Si los dos tokens son '
        . 'iguales, las ventas de una empresa se están firmando con el RUC de la otra: '
        . 'facturar a nombre de otro contribuyente.',
    );
});

it('una empresa conectada NO enciende la facturación de la otra', function () {
    Http::fake(['*/api/v1/ventas' => Http::response(respuestaEmisor(), 200)]);

    $conectada = TestEnv::crear();
    $conectada->conectarFacturacion(['token' => 'token-CONECTADA']);

    // La segunda empresa nunca pegó un código de vinculación.
    $sinConectar = TestEnv::crear();

    emitir(ventaEmitible($sinConectar, $sinConectar->abrirTurno()));

    expect(tokensEnviados())->toBeEmpty(
        'La empresa sin conexión no debe emitir nada. Con el flag global del `.env`, '
        . 'encender una empresa encendía las dos.',
    );

    expect(VentaComprobante::query()
        ->whereIn('venta_id', Venta::where('empresa_id', $sinConectar->empresa->id)->pluck('id'))
        ->count())->toBe(0);
});

it('el token se guarda CIFRADO: un vistazo a la tabla no permite emitir', function () {
    $env = TestEnv::crear();
    $env->conectarFacturacion(['token' => 'token-EN-CLARO']);

    $crudo = \Illuminate\Support\Facades\DB::table('facturacion_conexiones')
        ->where('empresa_id', $env->empresa->id)
        ->value('token');

    expect($crudo)->not->toBe('token-EN-CLARO',
        'El token es la credencial que firma comprobantes fiscales: un volcado de la '
        . 'base no puede filtrar la capacidad de emitir a nombre del contribuyente.',
    );

    // Y aun así se lee bien desde la aplicación.
    expect(ConexionFacturacion::where('empresa_id', $env->empresa->id)->first()->token)
        ->toBe('token-EN-CLARO');
});

// ══ 2 · GUARDA DEL RUC ══════════════════════════════════════════════════════

it('un código de OTRO contribuyente se rechaza y NO se guarda nada', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    // El emisor responde con un RUC que no es el de la empresa: es exactamente el
    // escenario de MacSoft/HYC visto desde la pantalla de configuración.
    Http::fake(['*/api/v1/vincular' => Http::response([
        'token'  => 'token-DE-OTRA-EMPRESA',
        'emisor' => [
            'ruc'          => '20600134648',
            'razon_social' => 'HYC FERROMATERIALES SRL',
            'modo'         => 'produccion',
        ],
    ], 200)]);

    $respuesta = $this->post(route('configuracion.facturacion.conectar'), ['codigo' => '7K2P-9X4Q']);

    $respuesta->assertSessionHasErrors('codigo');

    // NO se guarda nada. Dejar la fila "por si acaso" bastaría para que un despiste
    // posterior la activara y empezara a emitir con el RUC equivocado.
    expect(ConexionFacturacion::where('empresa_id', $env->empresa->id)->exists())->toBeFalse(
        'Con el RUC descuadrado no puede quedar NINGUNA fila: es la guarda que impide '
        . 'emitir a nombre de otro contribuyente.',
    );

    // El mensaje tiene que decir qué RUC se esperaba y cuál llegó; si no, quien lo
    // lee no sabe si se equivocó de código o de empresa.
    $mensaje = session('errors')->first('codigo');

    expect($mensaje)->toContain($env->empresa->ruc)
        ->and($mensaje)->toContain('20600134648');
});

it('un código de la MISMA empresa se acepta y guarda la conexión', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    Http::fake(['*/api/v1/vincular' => Http::response([
        'token'  => 'token-CORRECTO',
        'emisor' => [
            'ruc'          => $env->empresa->ruc,
            'razon_social' => $env->empresa->razon_social,
            'modo'         => 'produccion',
        ],
    ], 200)]);

    $this->post(route('configuracion.facturacion.conectar'), ['codigo' => '7K2P-9X4Q'])
        ->assertSessionHasNoErrors();

    $conexion = ConexionFacturacion::where('empresa_id', $env->empresa->id)->firstOrFail();

    expect($conexion->ruc_emisor)->toBe($env->empresa->ruc)
        ->and($conexion->token)->toBe('token-CORRECTO')
        // Conectar NO es emitir: el interruptor nace apagado incluso en producción.
        // Emitir mal es irreversible, así que se enciende a conciencia.
        ->and($conexion->emision_activa)->toBeFalse();
});

it('los códigos rechazados por el emisor se traducen a un mensaje que se entiende', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    Http::fake(['*/api/v1/vincular' => Http::response(['error' => 'codigo_expirado'], 410)]);

    $this->post(route('configuracion.facturacion.conectar'), ['codigo' => '7K2P-9X4Q'])
        ->assertSessionHasErrors('codigo');

    // Y no se le enseña el código del contrato a quien tiene el papel delante.
    expect(session('errors')->first('codigo'))
        ->toContain('caducó')
        ->and(session('errors')->first('codigo'))->not->toContain('codigo_expirado');

    expect(ConexionFacturacion::where('empresa_id', $env->empresa->id)->exists())->toBeFalse();
});

// ══ 3 · GUARDA DEL MODO ═════════════════════════════════════════════════════

/** Conecta por HTTP con el modo indicado y devuelve la conexión guardada. */
function conectarEnModo(TestEnv $env, string $modo): ?ConexionFacturacion
{
    Http::fake(['*/api/v1/vincular' => Http::response([
        'token'  => 'token-' . $modo,
        'emisor' => [
            'ruc'          => $env->empresa->ruc,
            'razon_social' => $env->empresa->razon_social,
            'modo'         => $modo,
        ],
    ], 200)]);

    test()->post(route('configuracion.facturacion.conectar'), ['codigo' => 'ABCD-1234'])
        ->assertSessionHasNoErrors();

    return ConexionFacturacion::where('empresa_id', $env->empresa->id)->first();
}

it('en BETA se conecta pero NO se puede activar la emisión', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    // Conectar SÍ se permite: hay que poder comprobar que la tubería funciona antes
    // de pasar la empresa a producción. Lo que queda bloqueado es EMITIR.
    $conexion = conectarEnModo($env, 'beta');

    expect($conexion)->not->toBeNull()
        ->and($conexion->modo)->toBe('beta')
        ->and($conexion->puedeEmitir())->toBeFalse();

    $this->put(route('configuracion.facturacion.emision'), ['emision_activa' => true])
        ->assertSessionHasErrors('emision_activa');

    expect($conexion->fresh()->emision_activa)->toBeFalse();

    expect(session('errors')->first('emision_activa'))
        ->toContain('modo de pruebas');
});

it('en SIMULACIÓN tampoco se puede activar la emisión', function () {
    // Bloquear los DOS modos de ensayo es decisión explícita: una boleta "de prueba"
    // que consume correlativo real deja un hueco que justificar ante SUNAT.
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    $conexion = conectarEnModo($env, 'simulacion');

    expect($conexion->modo)->toBe('simulacion');

    $this->put(route('configuracion.facturacion.emision'), ['emision_activa' => true])
        ->assertSessionHasErrors('emision_activa');

    expect($conexion->fresh()->emision_activa)->toBeFalse();
});

it('en PRODUCCIÓN la emisión se activa con normalidad', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    $conexion = conectarEnModo($env, 'produccion');

    expect($conexion->puedeEmitir())->toBeTrue();

    $this->put(route('configuracion.facturacion.emision'), ['emision_activa' => true])
        ->assertSessionHasNoErrors();

    expect($conexion->fresh()->emision_activa)->toBeTrue()
        ->and($conexion->fresh()->emite())->toBeTrue();
});

it('una empresa en beta con el interruptor forzado a mano NO emite', function () {
    // Segunda línea de defensa: el modo lo cambia el emisor por su cuenta, así que
    // `emite()` lo comprueba en CADA venta y no solo al pulsar el interruptor.
    Http::fake(['*/api/v1/ventas' => Http::response(respuestaEmisor(), 200)]);

    $env = TestEnv::crear();
    $env->conectarFacturacion(['modo' => 'beta', 'emision_activa' => true]);

    emitir(ventaEmitible($env, $env->abrirTurno()));

    expect(tokensEnviados())->toBeEmpty(
        'Con el emisor en beta no puede salir NADA hacia SUNAT, aunque la fila diga '
        . 'emision_activa = true.',
    );
});

// ══ 4 · LOS JOBS, SIN SESIÓN ════════════════════════════════════════════════

it('el job de emisión resuelve la empresa de la VENTA, no la del usuario autenticado', function () {
    Http::fake(['*/api/v1/ventas' => Http::response(respuestaEmisor(), 200)]);

    $macsoft = TestEnv::crear();
    $macsoft->conectarFacturacion(['token' => 'token-MACSOFT']);

    $hyc = TestEnv::crear();
    $hyc->conectarFacturacion(['token' => 'token-HYC']);

    $venta = ventaEmitible($hyc, $hyc->abrirTurno());

    // Un admin de MacSoft con la sesión abierta. Si el job dedujera la empresa del
    // usuario autenticado, la boleta de HYC saldría con el token de MacSoft — que es
    // el fallo original, ahora por otra puerta. En el worker real ni siquiera hay
    // sesión: `auth()->user()` es null y no habría de dónde deducirla.
    $this->actingAs($macsoft->admin);

    emitir($venta);

    expect(tokensEnviados())->toBe(['token-HYC']);
});

it('el job de polling exige la empresa y descarta un comprobante que no es suyo', function () {
    Http::fake(['*/api/v1/ventas/*' => Http::response(respuestaEmisor(), 200)]);

    $macsoft = TestEnv::crear();
    $macsoft->conectarFacturacion(['token' => 'token-MACSOFT']);

    $hyc = TestEnv::crear();
    $hyc->conectarFacturacion(['token' => 'token-HYC']);

    $venta = ventaEmitible($hyc, $hyc->abrirTurno());

    $ce = VentaComprobante::create([
        'venta_id'      => $venta->id,
        'tipo'          => '03',
        'estado'        => 'pendiente_resumen',
        'facturamac_id' => 4321,
        'numero'        => 'B002-00000009',
    ]);

    // Empresa correcta: consulta con el token de HYC.
    (new ConsultarEstadoComprobante($ce->id, $hyc->empresa->id))->handle(app(FacturacionEmpresa::class));

    expect(tokensEnviados())->toBe(['token-HYC']);

    // Empresa equivocada: NO se pregunta. Con el id del comprobante y un token ajeno
    // se estaría consultando al emisor por un documento de otro contribuyente.
    Http::fake(['*/api/v1/ventas/*' => Http::response(respuestaEmisor(), 200)]);

    (new ConsultarEstadoComprobante($ce->id, $macsoft->empresa->id))->handle(app(FacturacionEmpresa::class));

    expect(tokensEnviados())->toBeEmpty();
});

it('la nota de crédito viaja con la empresa de la VENTA, no con la de quien devuelve', function () {
    $env = TestEnv::crear(['permite_devoluciones' => true, 'requiere_aprobacion_devolucion' => false]);
    $env->conectarFacturacion(['token' => 'token-DEL-VENDEDOR']);

    $turno = $env->abrirTurno();
    $venta = ventaEmitible($env, $turno);

    VentaComprobante::create([
        'venta_id'      => $venta->id,
        'tipo'          => '03',
        'estado'        => 'aceptado',
        'facturamac_id' => 4321,
        'numero'        => 'B002-00000009',
    ]);

    app(DevolucionService::class)->crear([
        'venta_id'  => $venta->id,
        'motivo_id' => $env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items'     => [[
            'venta_item_id' => $venta->items()->first()->id,
            'cantidad'      => 1,
            'restock'       => true,
        ]],
    ], $env->admin);

    // El job se encola con la empresa DE LA VENTA en el payload: sin eso, en el
    // worker no habría de dónde sacarla (no hay sesión) y la nota de crédito saldría
    // con el token de quien tocara.
    Queue::assertPushed(EmitirNotaCreditoElectronica::class,
        fn ($job) => $job->empresaId === $env->empresa->id);
});

// ══ 5 · SIN LA TABLA CREADA ═════════════════════════════════════════════════

it('sin la tabla facturacion_conexiones el POS funciona como con el módulo apagado', function () {
    // Código desplegado ANTES de ejecutar el SQL en el servidor. Esta forma exacta
    // de fallo —"relation does not exist" en la pantalla más usada del POS— ya tumbó
    // el sistema una vez con `venta_comprobantes`.
    //
    // La tabla se borra DENTRO de la transacción del test: PostgreSQL admite DDL
    // transaccional, así que `DatabaseTransactions` la restaura al terminar. Es más
    // honesto que simular el error: se prueba la situación real.
    Http::fake();

    $env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $env->conectarFacturacion();   // conectada... y aun así la tabla desaparece
    $this->actingAs($env->admin);
    $env->abrirTurno($env->admin);

    Schema::drop('facturacion_conexiones');

    // Contenedor limpio: si no, la memoria del singleton diría que la tabla existe.
    app()->forgetInstance(FacturacionEmpresa::class);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facturacion.enabled', false)
            ->where('facturacion.modo', 'desactivado')
            ->where('facturacion.produccion', false));

    $this->get(route('ventas.index'))->assertOk();

    $emisor   = parse_url(config('facturamac.base_url'), PHP_URL_HOST);
    $alEmisor = collect(Http::recorded())
        ->map(fn ($par) => $par[0]->url())
        ->filter(fn ($url) => str_contains($url, $emisor))
        ->values()
        ->all();

    expect($alEmisor)->toBeEmpty();
});

it('sin la tabla, emitir una venta no revienta: simplemente no emite', function () {
    Http::fake();

    $env = TestEnv::crear();
    $env->conectarFacturacion();
    $venta = ventaEmitible($env, $env->abrirTurno());

    Schema::drop('facturacion_conexiones');
    app()->forgetInstance(FacturacionEmpresa::class);

    emitir($venta);

    expect(tokensEnviados())->toBeEmpty();
    expect(VentaComprobante::where('venta_id', $venta->id)->exists())->toBeFalse();
});

// ══ 6 · LA PANTALLA ═════════════════════════════════════════════════════════

it('la pantalla muestra emisor, RUC, modo y si la emisión está activa', function () {
    $env = TestEnv::crear();
    $env->conectarFacturacion([
        'token'               => 'token-VISIBLE',
        'razon_social_emisor' => 'MACSOFT E.I.R.L.',
        'modo'                => 'produccion',
        'emision_activa'      => true,
    ]);
    $this->actingAs($env->admin);

    $this->get(route('configuracion.facturacion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Configuracion/FacturacionElectronica')
            ->where('conexion.ruc_emisor', $env->empresa->ruc)
            ->where('conexion.razon_social_emisor', 'MACSOFT E.I.R.L.')
            ->where('conexion.modo', 'produccion')
            ->where('conexion.emision_activa', true)
            ->where('conexion.emite', true)
            ->where('instalado', true)
            // El token NO sale hacia el navegador en ninguna forma: es la credencial
            // que firma comprobantes fiscales y acabaría en el HTML de la página.
            ->missing('conexion.token'));
});

it('desconectar borra el token y para la emisión', function () {
    $env = TestEnv::crear();
    $env->conectarFacturacion();
    $this->actingAs($env->admin);

    $this->delete(route('configuracion.facturacion.desconectar'))
        ->assertSessionHasNoErrors();

    expect(ConexionFacturacion::where('empresa_id', $env->empresa->id)->exists())->toBeFalse();
    expect((new FacturacionEmpresa())->activa($env->empresa->id))->toBeFalse();
});
