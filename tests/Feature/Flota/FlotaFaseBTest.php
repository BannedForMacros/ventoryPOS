<?php

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\CuentaMovimiento;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\Vehiculo;
use App\Models\VehiculoCombustible;
use App\Models\VehiculoDocumento;
use App\Models\Venta;
use App\Services\FlotaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestEnv;

/**
 * Flota, Fase B: combustible y mantenimiento son GASTOS de siempre con una ficha
 * técnica; el km del camión sale de esas fichas; alertas y utilidad por placa.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['usa_flota' => true]);

    $tipo = GastoTipo::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Flota', 'categoria' => 'operativo', 'activo' => true]);
    $this->tipo = $tipo;
    $this->combustible = GastoConcepto::create(['empresa_id' => $this->env->empresa->id, 'gasto_tipo_id' => $tipo->id, 'nombre' => 'Petróleo', 'activo' => true, 'flota_uso' => 'combustible']);
    $this->mant = GastoConcepto::create(['empresa_id' => $this->env->empresa->id, 'gasto_tipo_id' => $tipo->id, 'nombre' => 'Mantenimiento', 'activo' => true, 'flota_uso' => 'mantenimiento']);
    $this->peaje = GastoConcepto::create(['empresa_id' => $this->env->empresa->id, 'gasto_tipo_id' => $tipo->id, 'nombre' => 'Peaje', 'activo' => true]);

    $this->chofer = Conductor::create(['empresa_id' => $this->env->empresa->id, 'tipo' => 'chofer', 'nombres' => 'Jonathan', 'numero_documento' => '44556677', 'licencia' => 'Q1']);
    $this->camion = Vehiculo::create(['empresa_id' => $this->env->empresa->id, 'placa' => 'M9C-822', 'capacidad_kg' => 13000, 'estado' => 'disponible', 'conductor_id' => $this->chofer->id, 'mant_intervalo_km' => 8000]);
});

function fbGasto($test, GastoConcepto $concepto, float $monto, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $test->from(route('gastos.index'))->post(route('gastos.store'), array_merge([
        'gasto_tipo_id' => $test->tipo->id, 'gasto_concepto_id' => $concepto->id, 'monto' => $monto,
        'fecha' => now()->toDateString(), 'turno_id' => $test->turno->id,
        'metodo_pago_id' => $test->env->metodo('efectivo')->id, 'vehiculo_id' => $test->camion->id,
    ], $extra));
}

function fbCarga($test, float $km, float $galones, float $monto = 100, bool $lleno = true, ?string $fecha = null): \Illuminate\Testing\TestResponse
{
    return fbGasto($test, $test->combustible, $monto, [
        'fecha' => $fecha ?? now()->toDateString(),
        // Días pasados: gasto administrativo (un gasto de turno lleva la fecha del turno).
        'turno_id' => $fecha ? null : $test->turno->id,
        'combustible' => ['km' => $km, 'galones' => $galones, 'tanque_lleno' => $lleno, 'conductor_id' => $test->chofer->id, 'grifo' => 'Primax'],
    ]);
}

function fbKm($test): float
{
    return (float) app(FlotaService::class)->kmActual([$test->camion->id])[$test->camion->id];
}

it('una carga de combustible es un gasto normal con su ficha técnica', function () {
    fbCarga($this, 84210, 30, 450)->assertSessionHasNoErrors();

    $gasto = Gasto::latest('id')->firstOrFail();
    expect($gasto->vehiculo_id)->toBe($this->camion->id)
        ->and($gasto->combustible)->not->toBeNull()
        ->and((float) $gasto->combustible->galones)->toBe(30.0)
        ->and($gasto->combustible->grifo)->toBe('Primax')
        // El dinero va por el camino de siempre: egreso en tesorería.
        ->and(CuentaMovimiento::where('ref_tipo', 'gasto')->where('ref_id', $gasto->id)->where('tipo', 'egreso')->sum('monto'))->toEqual(450)
        ->and(fbKm($this))->toBe(84210.0);
});

it('combustible sin placa o sin galones no se guarda (ni el gasto)', function () {
    fbCarga($this, 1000, 10)->assertSessionHasNoErrors();
    $antes = Gasto::count();

    fbGasto($this, $this->combustible, 100, ['vehiculo_id' => null, 'combustible' => ['km' => 1200, 'galones' => 10]])->assertSessionHasErrors('vehiculo_id');
    fbGasto($this, $this->combustible, 100, ['combustible' => ['km' => 1200]])->assertSessionHasErrors('combustible.galones');

    expect(Gasto::count())->toBe($antes);
});

it('el km no puede retroceder respecto a la carga anterior', function () {
    fbCarga($this, 5000, 20)->assertSessionHasNoErrors();
    $antes = [Gasto::count(), CuentaMovimiento::where('ref_tipo', 'gasto')->count()];

    fbCarga($this, 4900, 20)->assertSessionHasErrors('combustible.km');

    // Nada a medias: ni el gasto ni su egreso.
    expect([Gasto::count(), CuentaMovimiento::where('ref_tipo', 'gasto')->count()])->toBe($antes);
});

it('el rendimiento se calcula entre tanques llenos', function () {
    fbCarga($this, 1000, 10, 150, true, now()->subDays(3)->toDateString())->assertSessionHasNoErrors();
    fbCarga($this, 1200, 5, 75, false, now()->subDays(2)->toDateString())->assertSessionHasNoErrors();
    fbCarga($this, 1400, 10, 150, true, now()->subDay()->toDateString())->assertSessionHasNoErrors();

    $r = app(FlotaService::class)->rendimiento($this->camion->id);
    $ultima = $r['cargas'][0];

    // 400 km con 15 galones (5 + 10 desde el lleno anterior)
    expect($ultima['km'])->toBe(1400.0)
        ->and($ultima['km_recorridos'])->toBe(200.0)
        ->and($ultima['rendimiento'])->toBe(26.67)
        ->and($ultima['costo_km'])->toBe(0.563)              // S/ 225 / 400 km
        ->and($r['cargas'][1]['rendimiento'])->toBeNull();    // no llenó el tanque
});

it('un gasto eliminado deja de contar y vuelve al reactivarlo', function () {
    fbCarga($this, 1000, 10)->assertSessionHasNoErrors();
    fbCarga($this, 2000, 10)->assertSessionHasNoErrors();
    $ultimo = Gasto::latest('id')->firstOrFail();

    $this->delete(route('gastos.destroy', $ultimo->id))->assertSessionHasNoErrors();
    expect(fbKm($this))->toBe(1000.0);

    $this->post(route('gastos.restore', $ultimo->id))->assertSessionHasNoErrors();
    expect(fbKm($this))->toBe(2000.0);
});

it('editar el gasto corrige su ficha; cambiar a otro concepto la quita', function () {
    fbCarga($this, 1000, 10, 150)->assertSessionHasNoErrors();
    $g = Gasto::latest('id')->firstOrFail();

    $this->put(route('gastos.update', $g->id), [
        'gasto_tipo_id' => $this->tipo->id, 'gasto_concepto_id' => $this->combustible->id, 'monto' => 150,
        'fecha' => $g->fecha->toDateString(), 'comentario' => '', 'vehiculo_id' => $this->camion->id,
        'combustible' => ['km' => 1050, 'galones' => 12, 'tanque_lleno' => true],
    ])->assertSessionHasNoErrors();
    expect((float) $g->fresh()->combustible->km)->toBe(1050.0);

    $this->put(route('gastos.update', $g->id), [
        'gasto_tipo_id' => $this->tipo->id, 'gasto_concepto_id' => $this->peaje->id, 'monto' => 150,
        'fecha' => $g->fecha->toDateString(), 'comentario' => '', 'vehiculo_id' => $this->camion->id,
    ])->assertSessionHasNoErrors();
    expect(VehiculoCombustible::where('gasto_id', $g->id)->exists())->toBeFalse()
        ->and($g->fresh()->vehiculo_id)->toBe($this->camion->id);   // sigue cargado a la placa
});

it('el contador del preventivo avanza con las cargas y avisa', function () {
    fbGasto($this, $this->mant, 600, ['mantenimiento' => ['tipo' => 'preventivo', 'km' => 8000, 'taller' => 'Hyundai']])->assertSessionHasNoErrors();
    $f = app(FlotaService::class);

    fbCarga($this, 15600, 30)->assertSessionHasNoErrors();
    $m = $f->mantenimiento($this->camion->fresh());
    expect($m['proximo_km'])->toBe(16000.0)->and($m['faltan'])->toBe(400.0)->and($m['estado'])->toBe('pronto');
    expect(collect($f->alertas($this->env->empresa->id))->firstWhere('tipo', 'mantenimiento')['nivel'])->toBe('ambar');

    fbCarga($this, 16100, 30)->assertSessionHasNoErrors();
    expect($f->mantenimiento($this->camion->fresh())['estado'])->toBe('vencido');

    // Preventivo sin km: no se puede programar el siguiente.
    fbGasto($this, $this->mant, 100, ['mantenimiento' => ['tipo' => 'preventivo']])->assertSessionHasErrors('mantenimiento.km');
});

it('documentos con archivo privado, semáforo y alerta; renovar apaga el aviso', function () {
    $this->withoutVite();
    Storage::fake('local');

    $this->post(route('flota.documentos.store', $this->camion->id), [
        'tipo' => 'SOAT', 'numero' => 'S-1', 'vence' => now()->addDays(10)->toDateString(),
        'archivo' => UploadedFile::fake()->create('soat.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $doc = VehiculoDocumento::firstOrFail();
    expect($doc->estado())->toBe('por_vencer');
    Storage::disk('local')->assertExists($doc->archivo);
    $this->get(route('flota.documentos.archivo', $doc->id))->assertOk();

    $alertas = collect(app(FlotaService::class)->alertas($this->env->empresa->id));
    expect($alertas->where('tipo', 'documento')->count())->toBe(1);

    // Renovado: el SOAT nuevo vigente reemplaza al viejo en las alertas.
    $this->post(route('flota.documentos.store', $this->camion->id), ['tipo' => 'SOAT', 'vence' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
    expect(collect(app(FlotaService::class)->alertas($this->env->empresa->id))->where('tipo', 'documento')->count())->toBe(0);

    // Otra empresa no ve el archivo.
    $otra = TestEnv::crear();
    $otra->empresa->update(['usa_flota' => true]);
    $this->actingAs($otra->admin)->get(route('flota.documentos.archivo', $doc->id))->assertNotFound();
});

it('utilidad por placa: margen de lo despachado menos sus gastos', function () {
    $this->env->empresa->update(['usa_entregas' => true, 'entrega_config' => ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]]);
    $cliente = Cliente::create(['empresa_id' => $this->env->empresa->id, 'nombres' => 'Obra', 'tipo_documento' => 'DNI', 'numero_documento' => '16789011', 'activo' => true]);
    // Costo 6 (TestEnv), precio 10: margen 4 por unidad.
    $ladrillo = $this->env->crearProducto(['nombre' => 'King Kong', 'precio_venta' => 10, 'stock_inicial' => 500]);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'cliente_id' => $cliente->id, 'tipo_entrega' => 'envio', 'envio_entregado' => true,
        'cliente_direccion' => 'Obra', 'vehiculo_id' => $this->camion->id,
        'items' => [['producto_id' => $ladrillo->id, 'producto_unidad_id' => $ladrillo->unidadBase->id, 'cantidad' => 100, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 1000]],
    ])->assertSessionHasNoErrors();

    fbCarga($this, 1000, 10, 150)->assertSessionHasNoErrors();
    fbGasto($this, $this->peaje, 20)->assertSessionHasNoErrors();

    $u = collect(app(FlotaService::class)->utilidad($this->env->empresa->id, now()->startOfWeek()->toDateString(), now()->toDateString()))
        ->firstWhere('vehiculo_id', $this->camion->id);

    expect($u['viajes'])->toBe(1)
        ->and($u['venta'])->toEqual(1000)
        ->and($u['margen'])->toEqual(400)
        ->and($u['combustible'])->toEqual(150)
        ->and($u['otros'])->toEqual(20)
        ->and($u['utilidad'])->toEqual(230);
});

it('configurar qué conceptos son combustible y mantenimiento', function () {
    $this->put(route('flota.conceptos'), ['combustible' => [$this->peaje->id], 'mantenimiento' => [$this->mant->id]])->assertSessionHasNoErrors();

    expect($this->peaje->fresh()->flota_uso)->toBe('combustible')
        ->and($this->combustible->fresh()->flota_uso)->toBeNull()
        ->and($this->mant->fresh()->flota_uso)->toBe('mantenimiento');
});

it('las pantallas de la fase B abren', function () {
    $this->withoutVite();
    fbCarga($this, 1000, 10)->assertSessionHasNoErrors();

    $this->get(route('flota.resumen'))->assertOk();
    $this->get(route('flota.resumen', ['periodo' => 'mes']))->assertOk();
    $this->get(route('flota.vehiculos.show', $this->camion->id))->assertOk();
    $this->get(route('flota.vehiculos.index'))->assertOk();
    $this->get(route('gastos.index', ['vehiculo_id' => $this->camion->id]))->assertOk()
        ->assertInertia(fn ($p) => $p->has('flota.vehiculos', 1));
});

it('sin flota, el gasto ignora la placa y la ficha', function () {
    $this->env->empresa->update(['usa_flota' => false]);
    fbCarga($this, 1000, 10)->assertSessionHasNoErrors();

    expect(Gasto::latest('id')->first()->vehiculo_id)->toBeNull()
        ->and(VehiculoCombustible::count())->toBe(0);
});
