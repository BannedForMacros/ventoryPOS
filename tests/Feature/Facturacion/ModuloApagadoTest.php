<?php

use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * Con la facturación electrónica APAGADA, el módulo tiene que ser INERTE.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * `FACTURAMAC_ENABLED` es `false` por defecto y la migración del módulo puede no
 * estar aplicada. Aun así, `VentaController::index()` metía
 * `comprobanteElectronico` en el `with()` y `ComprobanteElectronicoController::estado()`
 * consultaba la tabla ANTES de mirar el flag. En una instalación así, eso es un
 * "relation venta_comprobantes does not exist" y un 500 en `/ventas`: la pantalla
 * más usada del POS, caída por un módulo que ni siquiera está encendido. Esta
 * forma exacta de fallo ya tumbó el sistema una vez.
 *
 * ─── Cómo se comprueba ───────────────────────────────────────────────────────
 *
 * La base de tests es la real (Postgres con DatabaseTransactions) y la tabla SÍ
 * existe: borrarla para el test sería tocar datos de producción. Así que en vez
 * de simular el error se comprueba su CAUSA, que es más estricto: se escuchan
 * todas las consultas SQL de la petición y se exige que NINGUNA mencione
 * `venta_comprobantes`. Si no se consulta la tabla, da igual que exista o no.
 */

beforeEach(function () {
    // Empresa SIN conectar: el default de una instalación recién desplegada. Antes
    // esto era `config(['facturamac.enabled' => false])`, el flag del `.env`; hoy el
    // interruptor es la ausencia de fila en `facturacion_conexiones`, que es lo que
    // de verdad ocurre en producción mientras nadie pega un código de vinculación.

    // Con el módulo apagado no debería salir NI UNA petición al emisor. Si sale,
    // Http::fake lo absorbe y la aserción de más abajo lo delata.
    Http::fake();

    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);

    $this->producto = $this->env->crearProducto([
        'precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50,
    ]);
    $this->turno = $this->env->abrirTurno($this->env->admin);

    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $this->turno);
});

/**
 * Ejecuta $accion registrando el SQL, y devuelve las consultas que tocaron
 * `venta_comprobantes`.
 *
 * @return list<string>
 */
function sqlSobreVentaComprobantes(callable $accion): array
{
    $tocadas = [];

    DB::listen(function ($q) use (&$tocadas) {
        if (str_contains($q->sql, 'venta_comprobantes')) {
            $tocadas[] = $q->sql;
        }
    });

    $accion();

    return $tocadas;
}

it('/ventas responde 200 sin tocar venta_comprobantes cuando el módulo está apagado', function () {
    $respuesta = null;

    $tocadas = sqlSobreVentaComprobantes(function () use (&$respuesta) {
        $respuesta = $this->get(route('ventas.index'));
    });

    $respuesta->assertOk();

    expect($tocadas)->toBeEmpty(
        'La lista de ventas consultó venta_comprobantes con el módulo apagado: '
        . 'en una instalación sin la migración aplicada eso es un 500 en la pantalla '
        . 'más usada del POS.',
    );
});

it('la lista sigue mandando la clave comprobante_electronico, en null', function () {
    // El frontend hace `ce(v)?.estado`: la clave tiene que existir aunque el
    // módulo esté apagado, o la tabla de ventas cambiaría de forma según el flag.
    $this->get(route('ventas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ventas.data.0.comprobante_electronico', null));
});

it('el endpoint de estado del comprobante responde sin consultar la tabla', function () {
    $venta = Venta::where('empresa_id', $this->env->empresa->id)->firstOrFail();

    $respuesta = null;
    $tocadas   = sqlSobreVentaComprobantes(function () use (&$respuesta, $venta) {
        $respuesta = $this->getJson(route('ventas.comprobante.estado', $venta));
    });

    $respuesta->assertOk()
        ->assertJson([
            'tiene_comprobante' => false,
            // Sin módulo, esta venta no va a tener comprobante NUNCA: el POS debe
            // dejar de preguntar en vez de hacer polling eterno.
            'emitible'          => false,
        ]);

    expect($tocadas)->toBeEmpty();
});

it('el POS se pinta sin llamar al emisor y sin aviso de producción', function () {
    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facturacion.enabled', false)
            // `desactivado` (no `desconocido`): no es que el emisor no conteste, es
            // que no hay a quién preguntar. El POS no pinta ninguna franja.
            ->where('facturacion.modo', 'desactivado')
            ->where('facturacion.produccion', false)
            // El umbral llega igual: es la regla legal que el POS aplica cuando el
            // módulo se encienda, y el frontend necesita un número.
            ->where('facturacion.umbral_boleta_identificada', 700));

    // OJO: no vale `Http::assertNothingSent()`. El POS SÍ hace una petición al
    // pintarse —el tipo de cambio del dólar a Decolecta— y es anterior e ajena a
    // la facturación. Lo que este test defiende es que no salga NADA hacia el
    // emisor, así que se filtra por su host en vez de exigir silencio absoluto.
    $emisor = parse_url(config('facturamac.base_url'), PHP_URL_HOST);

    $alEmisor = collect(Http::recorded())
        ->map(fn ($par) => $par[0]->url())
        ->filter(fn ($url) => str_contains($url, $emisor))
        ->values()
        ->all();

    expect($alEmisor)->toBeEmpty();
});
