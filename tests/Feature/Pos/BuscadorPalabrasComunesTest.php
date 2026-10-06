<?php

use Tests\Support\TestEnv;

/**
 * El buscador del POS ignora palabras comunes ("de", "para"...) solo si queda
 * otra palabra. Antes "para" a secas devolvía nada: en una botica es justo lo
 * que se teclea para buscar paracetamol.
 */
it('encuentra paracetamol al escribir solo "para"', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $paracetamol = $env->crearProducto(['nombre' => 'PARACETAMOL 500MG TABLETA']);

    $ids = collect($this->getJson(route('pos.productos', ['q' => 'para']))->assertOk()->json('productos'))->pluck('id');
    expect($ids)->toContain($paracetamol->id);

    // Con otra palabra, la común se sigue ignorando.
    $env->crearProducto(['nombre' => 'PASTA DENTAL COLGATE']);
    $nombres = collect($this->getJson(route('pos.productos', ['q' => 'pasta de']))->json('productos'))->pluck('nombre');
    expect($nombres)->toContain('PASTA DENTAL COLGATE');
});

it('con búsqueda, lo que empieza con lo escrito va primero y la paginación sigue por relevancia', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    foreach (range(1, 45) as $n) $env->crearProducto(['nombre' => sprintf('Accesorio para water %02d', $n)]);
    $env->crearProducto(['nombre' => 'Llave para amoladora']);
    $paracetamol = $env->crearProducto(['nombre' => 'PARACETAMOL 500MG TABLETA']);

    $r = $this->getJson(route('pos.productos', ['q' => 'para']))->assertOk()->json();
    expect($r['productos'][0]['id'])->toBe($paracetamol->id)->and($r['cursor'])->not->toBeNull();

    $sig = $this->getJson(route('pos.productos', ['q' => 'para', 'cursor' => $r['cursor']]))->assertOk()->json();
    $todos = collect($r['productos'])->merge($sig['productos'])->pluck('id');
    expect($todos)->toHaveCount(47)->and($todos->unique())->toHaveCount(47);
});
