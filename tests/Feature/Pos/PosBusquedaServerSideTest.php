<?php

use Tests\Support\TestEnv;

/**
 * Búsqueda server-side de productos y clientes para el POS.
 *
 * Estos endpoints alimentan el scroll infinito y el buscador del POS, reduciendo
 * el payload inicial de la pantalla. Protegen el contrato JSON que espera el
 * frontend: productos con stock/costos, clientes con dirección y flag general,
 * paginación por cursor.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->env->abrirTurno($this->env->admin);
});

it('el POS inicial ya no envía la lista completa de clientes', function () {
    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props)->not->toHaveKey('clientes');
            expect($props)->toHaveKeys(['clienteGeneral', 'categorias', 'hayServicios', 'productosHasMore', 'productosCursor']);
        });
});

it('pos.productos devuelve productos activos con stock y costo', function () {
    $p = $this->env->crearProducto(['nombre' => 'Martillo', 'stock_inicial' => 50, 'precio_costo' => 8.00]);

    $this->getJson(route('pos.productos'))
        ->assertOk()
        ->assertJsonPath('productos.0.id', $p->id)
        ->assertJsonPath('productos.0.nombre', 'Martillo')
        ->assertJsonPath('productos.0.stock_disponible', 50)
        ->assertJsonPath('productos.0.stock_costo_promedio', 8)
        ->assertJsonPath('has_more', false);
});

it('pos.productos filtra por nombre y por código', function () {
    $this->env->crearProducto(['nombre' => 'Martillo', 'codigo' => 'MART-001']);
    $this->env->crearProducto(['nombre' => 'Clavo', 'codigo' => 'CLAV-001']);

    $this->getJson(route('pos.productos', ['q' => 'mart']))
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.codigo', 'MART-001');

    $this->getJson(route('pos.productos', ['q' => 'CLAV-001']))
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Clavo');
});

it('pos.productos ignora artículos, tildes y orden al buscar por nombre', function () {
    $this->env->crearProducto(['nombre' => 'Bife de chorizo', 'codigo' => 'BIFE-01']);

    $this->getJson(route('pos.productos', ['q' => 'bife chorizo']))
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Bife de chorizo');

    $this->getJson(route('pos.productos', ['q' => 'chorizo bife']))
        ->assertOk()
        ->assertJsonCount(1, 'productos');

    $this->getJson(route('pos.productos', ['q' => 'bifé chorizo']))
        ->assertOk()
        ->assertJsonCount(1, 'productos');
});

it('pos.productos tolera typos solo en búsquedas de una sola palabra', function () {
    $this->env->crearProducto(['nombre' => 'Martillo', 'codigo' => 'MART-01']);

    $this->getJson(route('pos.productos', ['q' => 'martilo']))
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Martillo');
});

it('pos.productos en búsqueda multi-palabra exige que TODAS las palabras estén en el nombre', function () {
    $this->env->crearProducto(['nombre' => 'Ladrillo King Rojo', 'codigo' => 'LKR-01']);
    $this->env->crearProducto(['nombre' => 'Ladrillo King Kong', 'codigo' => 'LKK-01']);
    $this->env->crearProducto(['nombre' => 'Ladrillo Rojo', 'codigo' => 'LR-01']);

    // Tres palabras reales: solo debe coincidir el que tiene las tres.
    $this->getJson(route('pos.productos', ['q' => 'ladrillo king kong']))
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.codigo', 'LKK-01');

    // Dos palabras: requiere ambas.
    $this->getJson(route('pos.productos', ['q' => 'ladrillo rojo']))
        ->assertOk()
        ->assertJsonCount(2, 'productos');
});

it('pos.productos pagina por cursor', function () {
    // Crear suficientes productos para superar el límite por defecto de 40.
    for ($i = 1; $i <= 41; $i++) {
        $this->env->crearProducto(['nombre' => "Producto {$i} ZZ"]);
    }

    $r1 = $this->getJson(route('pos.productos'))->assertOk();
    expect($r1['productos'])->toHaveCount(40)
        ->and($r1['has_more'])->toBeTrue()
        ->and($r1['cursor'])->not->toBeNull();

    $r2 = $this->getJson(route('pos.productos', ['cursor' => $r1['cursor']]))->assertOk();
    expect($r2['productos'])->toHaveCount(1)
        ->and($r2['has_more'])->toBeFalse();
});

it('pos.clientes devuelve el cliente general primero y los campos necesarios', function () {
    $general = $this->env->clienteGeneral;
    $cliente = App\Models\Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'RUC',
        'numero_documento'   => '20612792438',
        'razon_social'       => 'ACME SAC',
        'direccion'          => 'Av. Principal 123',
        'es_cliente_general' => false,
        'activo'             => true,
    ]);

    $this->getJson(route('pos.clientes'))
        ->assertOk()
        ->assertJsonPath('clientes.0.id', $general->id)
        ->assertJsonPath('clientes.0.es_cliente_general', true)
        ->assertJsonPath('clientes.1.id', $cliente->id)
        ->assertJsonPath('clientes.1.direccion', 'Av. Principal 123');
});

it('pos.clientes filtra por nombre, apellidos y número de documento', function () {
    App\Models\Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '12345678',
        'nombres'            => 'Juan',
        'apellidos'          => 'Perez',
        'es_cliente_general' => false,
        'activo'             => true,
    ]);

    App\Models\Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'RUC',
        'numero_documento'   => '20612792439',
        'razon_social'       => 'Empresa ABC',
        'es_cliente_general' => false,
        'activo'             => true,
    ]);

    $this->getJson(route('pos.clientes', ['q' => 'perez']))
        ->assertOk()
        ->assertJsonCount(1, 'clientes')
        ->assertJsonPath('clientes.0.nombres', 'Juan');

    $this->getJson(route('pos.clientes', ['q' => 'perez juan']))
        ->assertOk()
        ->assertJsonCount(1, 'clientes')
        ->assertJsonPath('clientes.0.apellidos', 'Perez');

    $this->getJson(route('pos.clientes', ['q' => '20612792439']))
        ->assertOk()
        ->assertJsonCount(1, 'clientes')
        ->assertJsonPath('clientes.0.razon_social', 'Empresa ABC');
});

it('pos.clientes ignora tildes y permite palabras en desorden', function () {
    App\Models\Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '12345679',
        'nombres'            => 'María',
        'apellidos'          => 'García López',
        'es_cliente_general' => false,
        'activo'             => true,
    ]);

    $this->getJson(route('pos.clientes', ['q' => 'maria garcia']))
        ->assertOk()
        ->assertJsonCount(1, 'clientes')
        ->assertJsonPath('clientes.0.apellidos', 'García López');

    $this->getJson(route('pos.clientes', ['q' => 'lopez garcia']))
        ->assertOk()
        ->assertJsonCount(1, 'clientes');
});

it('un usuario sin permiso de POS no puede consultar los endpoints', function () {
    $rol = App\Models\Rol::create([
        'empresa_id'  => $this->env->empresa->id,
        'nombre'      => 'Sin POS',
        'es_admin'    => false,
        'permisos'    => json_encode(['pos' => ['ver' => false]]),
        'activo'      => true,
    ]);
    $user = App\Models\User::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'rol_id'     => $rol->id,
        'name'       => 'User',
        'email'      => 'user+' . uniqid() . '@test.com',
        'password'   => bcrypt('secret'),
        'activo'     => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('pos.productos'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('pos.clientes'))
        ->assertForbidden();
});
