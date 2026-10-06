<?php

use App\Models\VisorVentaSesion;
use App\Services\VisorVentas\LectorClaude;
use App\Services\VisorVentas\LectorCuaderno;
use App\Services\VisorVentas\LecturaFallida;
use Illuminate\Http\UploadedFile;
use Tests\Support\TestEnv;

/**
 * Visor de ventas: leer con IA la foto del cuaderno. Función del plan con
 * límite diario; nunca registra ventas sola. El lector se simula: las
 * pruebas no gastan créditos de la API.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->paracetamol = $this->env->crearProducto(['nombre' => 'PARACETAMOL 500MG X 100 TAB']);
    $this->env->crearProducto(['nombre' => 'AMOXICILINA 500MG']);

    // Lector simulado: devuelve lo que la prueba diga, o falla si se pide.
    $this->lector = new class implements LectorCuaderno {
        public array $ventas = [];
        public ?string $falla = null;
        public int $llamadas = 0;

        public function leer(string $imagenBase64, string $mediaType): array
        {
            $this->llamadas++;
            if ($this->falla) throw new LecturaFallida($this->falla);

            return ['ventas' => $this->ventas, 'modelo' => 'simulado', 'tokens_entrada' => 1900, 'tokens_salida' => 800];
        }
    };
    $this->app->instance(LectorCuaderno::class, $this->lector);
});

/** La fecha tal como se lee arriba de la columna del cuaderno (hoy). Es solo un texto de la lectura: el sistema no depende de ella. */
function fechaCuaderno(): string
{
    return now()->format('d/m/y');
}

function activarVisor($test, int $limite = 2): void
{
    $test->env->empresa->update(['usa_visor_ventas' => true, 'visor_ventas_limite_diario' => $limite]);
}

function leerFoto($test)
{
    // Cada foto distinta (otra página): la misma foto exacta no se vuelve a leer.
    static $n = 0;
    $n++;

    return $test->postJson(route('pos.visor-ventas'), ['foto' => UploadedFile::fake()->image('cuaderno.jpg', 800 + $n, 1200)]);
}

it('no está disponible si la empresa no lo tiene en su plan', function () {
    leerFoto($this)->assertStatus(422)->assertJsonPath('message', 'El visor de ventas no está incluido en el plan de tu empresa.');
    expect($this->lector->llamadas)->toBe(0);
});

it('cruza lo leído con el catálogo: verde si es claro, rojo si no existe', function () {
    activarVisor($this);
    $this->lector->ventas = [[
        'fecha' => fechaCuaderno(), 'total' => 3.2,
        'items' => [
            ['cantidad' => 10, 'texto' => '10 paracetml', 'interpretacion' => 'paracetamol', 'seguro' => true],
            ['cantidad' => 1, 'texto' => 'guts', 'interpretacion' => null, 'seguro' => false],
        ],
    ]];

    $r = leerFoto($this)->assertOk()->json();

    expect($r['restantes'])->toBe(1);
    $items = $r['ventas'][0]['items'];
    expect($items[0]['estado'])->toBe('verde');
    expect($items[0]['candidatos'][0]['producto_id'])->toBe($this->paracetamol->id);
    expect($items[1]['estado'])->toBe('rojo');
    expect($r['ventas'][0]['total'])->toBe(3.2);
});

it('respeta el límite diario del plan con el mensaje pedido', function () {
    activarVisor($this, 2);
    $this->lector->ventas = [];

    leerFoto($this)->assertOk();
    leerFoto($this)->assertOk();
    leerFoto($this)->assertStatus(422)
        ->assertJsonPath('message', 'Su plan es solo para 2 sesiones máximas por día.')
        ->assertJsonPath('restantes', 0);

    expect($this->lector->llamadas)->toBe(2);
    expect(VisorVentaSesion::where('empresa_id', $this->env->empresa->id)->count())->toBe(2);
});

it('si la lectura falla, no gasta el cupo del día', function () {
    activarVisor($this, 1);
    $this->lector->falla = 'No se pudo leer la foto en este momento. Intenta de nuevo en unos minutos.';
    leerFoto($this)->assertStatus(422)->assertJsonPath('restantes', 1);

    $this->lector->falla = null;
    leerFoto($this)->assertOk()->assertJsonPath('restantes', 0);
});

it('el POS recibe el visor solo si la empresa lo tiene', function () {
    $this->get(route('pos.index'))->assertInertia(fn ($p) => $p->where('visorVentas', null));

    activarVisor($this, 2);
    $this->get(route('pos.index'))->assertInertia(fn ($p) => $p
        ->where('visorVentas.limite', 2)
        ->where('visorVentas.restantes', 2));
});

it('el buscador del POS trae productos puntuales por id (para cargar lo leído)', function () {
    $r = $this->getJson(route('pos.productos', ['ids' => [$this->paracetamol->id]]))->assertOk()->json();
    expect(collect($r['productos'])->pluck('id')->all())->toBe([$this->paracetamol->id]);
});

it('sin clave de la API avisa en español y no llama a nada', function () {
    config(['services.anthropic.api_key' => null]);
    expect(fn () => (new LectorClaude())->leer('x', 'image/jpeg'))
        ->toThrow(LecturaFallida::class, 'El visor de ventas aún no está configurado (falta la clave de la API). Avísale al administrador del sistema.');
});

function cobrarVentaDelCuaderno($test, string $texto, int $cantidad, float $total): void
{
    $test->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'idempotency_key'  => 'visor-' . uniqid('', true),
        'items' => [[
            'producto_id' => $test->paracetamol->id, 'producto_unidad_id' => $test->paracetamol->unidadBase->id,
            'cantidad' => $cantidad, 'precio_unitario' => round($total / $cantidad, 2),
        ]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $total]],
        'visor' => [
            'fecha' => fechaCuaderno(), 'total' => $total,
            'items' => [['texto' => $texto, 'producto_id' => $test->paracetamol->id, 'cantidad' => $cantidad]],
        ],
    ])->assertSessionHasNoErrors();
}

it('aprende lo que la cajera eligió: la próxima foto lo pone en verde solo', function () {
    activarVisor($this, 5);
    cobrarVentaDelCuaderno($this, '10 parcmol', 10, 100.00);

    // Ni la IA ni el catálogo lo reconocerían: lo reconoce lo aprendido.
    $this->lector->ventas = [['fecha' => now()->addDay()->format('d/m/y'), 'total' => 5, 'items' => [
        ['cantidad' => 5, 'texto' => '5 Parcmol', 'interpretacion' => null, 'seguro' => false],
    ]]];
    $item = leerFoto($this)->assertOk()->json('ventas.0.items.0');

    expect($item['estado'])->toBe('verde')
        ->and($item['aprendido'])->toBeTrue()
        ->and($item['candidatos'][0]['producto_id'])->toBe($this->paracetamol->id);
});

it('la misma foto se devuelve gratis (sin llamar a la API ni gastar cupo)', function () {
    activarVisor($this, 5);
    $this->lector->ventas = [['fecha' => fechaCuaderno(), 'total' => 10, 'items' => [
        ['cantidad' => 1, 'texto' => '1 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true],
    ]]];
    $foto = UploadedFile::fake()->image('cuaderno.jpg', 800, 1200);
    $this->postJson(route('pos.visor-ventas'), ['foto' => $foto])->assertOk()->assertJsonPath('restantes', 4);

    $this->postJson(route('pos.visor-ventas'), ['foto' => $foto])
        ->assertOk()
        ->assertJsonPath('restantes', 4)
        ->assertJsonPath('ventas.0.total', 10)
        ->assertJsonPath('aviso', fn ($m) => str_starts_with($m, 'Esta foto ya se leyó'));
    expect($this->lector->llamadas)->toBe(1);
});

it('una lectura que la API cobró aunque falle gasta el cupo, y hay un tope duro de intentos', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    activarVisor($this, 1);
    $lector = new class implements LectorCuaderno {
        public int $llamadas = 0;
        public bool $cobrada = true;
        public function leer(string $b, string $m): array
        {
            $this->llamadas++;
            throw new LecturaFallida('La página tiene demasiadas ventas para una sola foto.', 0, null, $this->cobrada, 'simulado', 5000, 8000);
        }
    };
    $this->app->instance(LectorCuaderno::class, $lector);

    leerFoto($this)->assertStatus(422)->assertJsonPath('restantes', 0);
    leerFoto($this)->assertStatus(422)->assertJsonPath('message', 'Su plan es solo para 1 sesiones máximas por día.');
    expect($lector->llamadas)->toBe(1);
    expect(VisorVentaSesion::where('empresa_id', $this->env->empresa->id)->value('tokens_salida'))->toBe(8000);

    // Fallos sin cobro (sin conexión) no gastan cupo, pero tampoco son infinitos.
    VisorVentaSesion::query()->delete();
    $lector->cobrada = false;
    foreach (range(1, 3) as $_) leerFoto($this)->assertStatus(422)->assertJsonPath('restantes', 1);
    leerFoto($this)->assertStatus(422)->assertJsonPath('message', 'Su plan es solo para 1 sesiones máximas por día.');
    expect($lector->llamadas)->toBe(4);
});

it('el límite por minuto es propio del visor y habla en español', function () {
    activarVisor($this, 50);
    foreach (range(1, 12) as $_) $this->getJson(route('pos.productos', ['q' => 'para']))->assertOk();   // el buscador no le quita cupo
    foreach (range(1, 4) as $_) leerFoto($this)->assertOk();
    leerFoto($this)->assertStatus(429)->assertJsonPath('message', 'Vas muy rápido. Espera un minuto y vuelve a intentar.');
});

it('un error inesperado del lector cierra la sesión y no deja el cupo colgado', function () {
    activarVisor($this, 1);
    $this->lector->falla = null;
    $this->app->instance(LectorCuaderno::class, new class implements LectorCuaderno {
        public function leer(string $b, string $m): array { throw new \RuntimeException('boom'); }
    });

    leerFoto($this)->assertStatus(422)
        ->assertJsonPath('message', 'No se pudo leer la foto en este momento. Intenta de nuevo en unos minutos.')
        ->assertJsonPath('restantes', 1);
    expect(VisorVentaSesion::where('empresa_id', $this->env->empresa->id)->value('estado'))->toBe('error');
});

it('acota lo que devuelve el lector: tipos raros, totales negativos y textos gigantes', function () {
    activarVisor($this);
    $this->lector->ventas = [
        ['fecha' => str_repeat('Domingo 05 de octubre ', 50), 'total' => -100000000, 'items' => [
            ['cantidad' => 'abc', 'texto' => str_repeat('paracetamol ', 2000), 'interpretacion' => null, 'seguro' => true],
            ['cantidad' => 5, 'texto' => ['no es texto'], 'interpretacion' => null, 'seguro' => true],
        ]],
        null,
        ['fecha' => null, 'total' => 3, 'items' => 'x'],
    ];

    $r = leerFoto($this)->assertOk()->json('ventas');
    expect($r)->toHaveCount(1);
    expect($r[0]['total'])->toBeNull()
        ->and(mb_strlen($r[0]['fecha']))->toBeLessThanOrEqual(20)
        ->and($r[0]['items'])->toHaveCount(1)
        ->and(mb_strlen($r[0]['items'][0]['texto']))->toBeLessThanOrEqual(120)
        ->and($r[0]['items'][0]['cantidad'])->toBeNull()
        ->and($r[0]['items'][0]['estado'])->not->toBe('verde');
});

it('solo aprende productos que de verdad quedaron en la venta, nunca palabras genéricas, y una sola vez por venta', function () {
    activarVisor($this, 5);
    $amoxi = \App\Models\Producto::where('empresa_id', $this->env->empresa->id)->where('nombre', 'AMOXICILINA 500MG')->first();
    $key = 'visor-' . uniqid('', true);
    $payload = [
        'tipo_comprobante' => 'ticket', 'idempotency_key' => $key,
        'items' => [['producto_id' => $this->paracetamol->id, 'producto_unidad_id' => $this->paracetamol->unidadBase->id, 'cantidad' => 10, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
        'visor' => ['fecha' => fechaCuaderno(), 'total' => 100, 'items' => [
            ['texto' => '10 paracetamol', 'producto_id' => $amoxi->id, 'cantidad' => 10],          // no está en la venta
            ['texto' => '10 pastillas', 'producto_id' => $this->paracetamol->id, 'cantidad' => 10],  // genérica
            ['texto' => '10 parcmol', 'producto_id' => $this->paracetamol->id, 'cantidad' => 10],
            'basura',
        ]],
    ];
    $this->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();   // reintento

    $aprendido = \DB::table('visor_ventas_aprendizaje')->where('empresa_id', $this->env->empresa->id)->pluck('veces', 'texto')->all();
    expect($aprendido)->toBe(['parcmol' => 1]);
    expect(\App\Models\VisorVentaCobrada::where('empresa_id', $this->env->empresa->id)->count())->toBe(1);
});

it('las sugerencias traen precio y costo de la unidad base para comparar con el cuaderno', function () {
    activarVisor($this);
    $this->lector->ventas = [['fecha' => null, 'total' => 3, 'items' => [
        ['cantidad' => 3, 'texto' => '3 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true],
    ]]];
    $c = leerFoto($this)->assertOk()->json('ventas.0.items.0.candidatos.0');
    expect($c)->toHaveKeys(['producto_id', 'nombre', 'precio', 'costo'])
        ->and((float) $c['precio'])->toBe(10.0);
});

/** Cobra la venta leída como lo hace el POS: con su lectura (sesion) y su posición (indice). */
function cobrarDeLectura($test, array $lectura, int $indice): void
{
    $venta = $lectura['ventas'][$indice];
    $cant  = $venta['items'][0]['cantidad'];
    $test->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'idempotency_key' => 'visor-' . uniqid('', true),
        'items' => [['producto_id' => $test->paracetamol->id, 'producto_unidad_id' => $test->paracetamol->unidadBase->id, 'cantidad' => $cant, 'precio_unitario' => round($venta['total'] / $cant, 2)]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $venta['total']]],
        'visor' => ['sesion' => $lectura['sesion'], 'indice' => $venta['indice'], 'fecha' => $venta['fecha'], 'total' => $venta['total'], 'huella' => $venta['huella'],
            'items' => [['texto' => $venta['items'][0]['texto'], 'producto_id' => $test->paracetamol->id, 'cantidad' => $cant]]],
    ])->assertSessionHasNoErrors();
}

function ventaIgual(): array
{
    return ['fecha' => fechaCuaderno(), 'total' => 20, 'items' => [['cantidad' => 2, 'texto' => '2 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true]]];
}

it('ventas iguales en la misma página (el mismo producto vendido varias veces): solo la cobrada sale cerrada', function () {
    activarVisor($this, 5);
    $this->lector->ventas = [ventaIgual(), ventaIgual(), ventaIgual()];
    $lectura = leerFoto($this)->assertOk()->json();
    expect(collect($lectura['ventas'])->pluck('indice')->all())->toBe([0, 1, 2]);

    cobrarDeLectura($this, $lectura, 1);   // cobra la del medio

    // Al volver al POS: la misma lectura, solo la del medio cerrada, sin gastar otra.
    $this->get(route('pos.index'))->assertInertia(fn ($p) => $p
        ->where('visorVentas.restantes', 4)
        ->where('visorVentas.ultima.sesion', $lectura['sesion'])
        ->where('visorVentas.ultima.ventas.0.ya_cobrada', null)
        ->where('visorVentas.ultima.ventas.1.ya_cobrada.venta', fn ($n) => is_string($n))
        ->where('visorVentas.ultima.ventas.2.ya_cobrada', null));
    expect($this->lector->llamadas)->toBe(1);

    // Justo antes de cargar: la 1 y la 3 están libres; la 2 ya se cobró.
    $verificar = fn (int $i) => $this->postJson(route('pos.visor-ventas.verificar'), ['sesion' => $lectura['sesion'], 'indice' => $i])->assertOk()->json('ya_cobrada');
    expect($verificar(0))->toBeNull()->and($verificar(1))->not->toBeNull()->and($verificar(2))->toBeNull();
});

it('otra hoja del mismo día con una venta igual suelta NO sale como cobrada', function () {
    activarVisor($this, 5);
    $this->lector->ventas = [ventaIgual(), ['fecha' => fechaCuaderno(), 'total' => 5, 'items' => [['cantidad' => 1, 'texto' => '1 amoxicilina', 'interpretacion' => 'amoxicilina', 'seguro' => true]]]];
    cobrarDeLectura($this, leerFoto($this)->assertOk()->json(), 0);

    // Hoja 2: trae otra "2 paracetamol S/ 20" (otra venta) y cosas distintas.
    $this->lector->ventas = [['fecha' => fechaCuaderno(), 'total' => 7, 'items' => [['cantidad' => 7, 'texto' => '7 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true]]], ventaIgual()];
    $hoja2 = leerFoto($this)->assertOk()->json('ventas');
    expect($hoja2[1]['ya_cobrada'])->toBeNull()->and($hoja2[1]['posible_cobrada'])->toBeNull();
});

it('otra foto de la MISMA página: avisa (sin cerrar) las ventas que ya se cobraron', function () {
    activarVisor($this, 5);
    $pagina = [ventaIgual(), ['fecha' => fechaCuaderno(), 'total' => 30, 'items' => [['cantidad' => 3, 'texto' => '3 paracetamol', 'interpretacion' => 'paracetamol', 'seguro' => true]]]];
    $this->lector->ventas = $pagina;
    $lectura = leerFoto($this)->assertOk()->json();
    cobrarDeLectura($this, $lectura, 0);
    cobrarDeLectura($this, $lectura, 1);

    // Otra foto (otro ángulo) de la misma página: la página entera coincide.
    $otra = leerFoto($this)->assertOk()->json();
    expect($otra['sesion'])->not->toBe($lectura['sesion']);
    expect($otra['ventas'][0]['ya_cobrada'])->toBeNull()
        ->and($otra['ventas'][0]['posible_cobrada']['venta'])->toBeString()
        ->and($otra['ventas'][1]['posible_cobrada']['venta'])->toBeString();
});

it('la misma foto que leyó otra cajera: le queda su propia copia gratis y la recupera al volver al POS', function () {
    activarVisor($this, 5);
    $this->lector->ventas = [ventaIgual()];
    $foto = UploadedFile::fake()->image('cuaderno.jpg', 801, 1201);
    $original = $this->postJson(route('pos.visor-ventas'), ['foto' => $foto])->assertOk()->json();

    $cajera = \App\Models\User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $this->env->admin->rol_id,
        'name' => 'Otra cajera', 'email' => 'otra+' . uniqid() . '@test.com', 'password' => bcrypt('secret'),
        'email_verified_at' => now(), 'activo' => true,
    ]);
    $this->actingAs($cajera);
    $copia = $this->postJson(route('pos.visor-ventas'), ['foto' => $foto])->assertOk()->json();
    expect($copia['sesion'])->toBe($original['sesion'])->and($copia['restantes'])->toBe(4);
    expect($this->lector->llamadas)->toBe(1);

    // Al volver al POS (ultimaLectura es por persona y día) su revisión sigue ahí.
    expect(app(\App\Services\VisorVentas\VisorVentasService::class)->ultimaLectura($cajera->fresh())['sesion'])->toBe($original['sesion']);
});

