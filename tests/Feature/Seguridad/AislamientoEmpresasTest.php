<?php

use App\Models\Modulo;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Aislamiento multiempresa: con la sesión de una empresa no se puede tocar ni
 * usar nada de otra, aunque se cambie el id en la URL o en el formulario.
 */
beforeEach(function () {
    $this->a = TestEnv::crear();
    $this->b = TestEnv::crear();
    $this->actingAs($this->a->admin);
});

/** Cuenta + vínculo con un medio de pago no efectivo de la empresa del env. */
function cuentaDePago(TestEnv $env): int
{
    $metodo = $env->metodo('transferencia');
    $cuentaId = DB::table('cuentas')->insertGetId([
        'empresa_id' => $env->empresa->id, 'nombre' => 'Banco ' . uniqid(), 'activo' => true, 'es_efectivo' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('cuenta_metodo_pago')->insertGetId(['cuenta_id' => $cuentaId, 'metodo_pago_id' => $metodo->id]);
}

it('no deja editar una categoría de otra empresa', function () {
    $this->put(route('catalogo.categorias.update', $this->b->categoria), ['nombre' => 'Hackeada'])
        ->assertNotFound();

    expect($this->b->categoria->fresh()->nombre)->not->toBe('Hackeada');
});

it('no deja borrar una unidad de medida de otra empresa', function () {
    $this->delete(route('catalogo.unidades-medida.destroy', $this->b->unidad))->assertNotFound();

    expect($this->b->unidad->fresh())->not->toBeNull();
});

it('no deja abrir un turno en la caja de otra empresa', function () {
    $this->post(route('turnos.abrir'), ['caja_id' => $this->b->caja->id, 'monto_apertura' => 100])
        ->assertSessionHasErrors('caja_id');
});

it('no deja registrar una entrada con un producto de otra empresa', function () {
    $ajeno = $this->b->crearProducto();

    $this->post(route('inventario.entradas.store'), [
        'almacen_id' => $this->a->almacen->id,
        'tipo'       => 'compra',
        'fecha'      => now()->toDateString(),
        'detalles'   => [['producto_id' => $ajeno->id, 'unidad_medida_id' => $this->a->unidad->id, 'cantidad' => 5, 'costo_unitario' => 1]],
    ])->assertSessionHasErrors('detalles.0.producto_id');
});

it('tesorería nunca usa la cuenta de pago de otra empresa', function () {
    $pivoteAjeno = cuentaDePago($this->b);
    $cuentaAjena = DB::table('cuenta_metodo_pago')->where('id', $pivoteAjeno)->value('cuenta_id');

    $cuenta = app(TesoreriaService::class)->resolverCuenta($this->a->empresa->id, $pivoteAjeno, $this->a->metodo('efectivo')->id);

    expect($cuenta)->not->toBe((int) $cuentaAjena);
    expect(DB::table('cuentas')->where('id', $cuenta)->value('empresa_id'))->toBe($this->a->empresa->id);
});

it('tesorería rechaza un medio de pago de otra empresa', function () {
    app(TesoreriaService::class)->resolverCuenta($this->a->empresa->id, null, $this->b->metodo('transferencia')->id);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('la cuenta de pago de un gasto debe ser de la empresa', function () {
    $this->post(route('gastos.store'), [
        'cuenta_metodo_pago_id' => cuentaDePago($this->b),
        'monto' => 10, 'descripcion' => 'x', 'fecha' => now()->toDateString(),
    ])->assertSessionHasErrors('cuenta_metodo_pago_id');
});

it('un administrador de empresa no puede tocar los módulos del menú (son de todos)', function () {
    $modulo = Modulo::first() ?? Modulo::create(['nombre' => 'Prueba', 'slug' => 'prueba-' . uniqid(), 'activo' => true, 'orden' => 99]);

    $this->put(route('configuracion.modulos.update', $modulo), ['nombre' => 'Roto'])->assertForbidden();

    expect($modulo->fresh()->nombre)->not->toBe('Roto');
});

it('avisa de productos parecidos solo de la propia empresa (ignora mayúsculas y tildes)', function () {
    $this->a->crearProducto(['nombre' => 'Ladrillo Estándar 18 Huecos LARK']);
    $this->b->crearProducto(['nombre' => 'LADRILLO ESTANDAR 18 HUECOS LARK (otra empresa)']);

    $r = $this->getJson(route('catalogo.productos.parecidos', ['nombre' => 'LADRILLO ESTANDAR 18 HUECOS LARK']))->assertOk();

    expect(collect($r->json('productos'))->pluck('nombre')->all())->toBe(['Ladrillo Estándar 18 Huecos LARK']);
});
