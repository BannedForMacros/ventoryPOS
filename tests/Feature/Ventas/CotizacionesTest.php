<?php

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * Módulo Cotizaciones: crear/editar con totales, transiciones de estado con
 * auditoría, marcado automático de vencidas y conversión a venta vía POS
 * (ventas.store con cotizacion_id).
 */
beforeEach(function () {
    $this->env = TestEnv::crear(); // tasa_igv 18 por defecto
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '45678912',
        'nombres'          => 'Rosa',
        'apellidos'        => 'Jibaja',
        'telefono'         => '987654321',
        'activo'           => true,
    ]);

    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
});

function payloadCotizacion(array $overrides = []): array
{
    return array_merge([
        'cliente_id'        => test()->cliente->id,
        'referencia'        => 'Obra Av. Grau',
        'fecha'             => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(7)->toDateString(),
        'items'             => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
            'descuento_item'     => 0,
        ]],
    ], $overrides);
}

it('crea una cotización con items, totales y correlativo por empresa', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())
        ->assertSessionHasNoErrors();

    $cot = Cotizacion::where('empresa_id', $this->env->empresa->id)->first();
    expect($cot)->not->toBeNull();
    expect($cot->numero)->toBe('COT-0001');
    expect($cot->estado)->toBe('vigente');
    expect($cot->referencia)->toBe('Obra Av. Grau');
    expect((float) $cot->subtotal)->toBe(20.0);
    expect((float) $cot->descuento_total)->toBe(0.0);
    expect((float) $cot->total)->toBe(20.0);
    // Precio con IGV incluido: 20 / 1.18 × 0.18 = 3.05 (desglose informativo)
    expect((float) $cot->igv)->toBe(3.05);

    // Snapshot del catálogo en la línea
    expect($cot->items)->toHaveCount(1);
    expect($cot->items->first()->producto_nombre)->toBe($this->producto->nombre);
    expect((float) $cot->items->first()->subtotal)->toBe(20.0);

    // Auditoría
    expect(Auditoria::where('accion', 'cotizacion.creada')->where('modelo_id', $cot->id)->exists())->toBeTrue();

    // Correlativo: la segunda es COT-0002
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    expect(Cotizacion::where('empresa_id', $this->env->empresa->id)->orderByDesc('id')->first()->numero)->toBe('COT-0002');
});

it('aplica descuentos por línea al total y los suma en descuento_total', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion([
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 3,
            'precio_unitario'    => 10,
            'descuento_item'     => 2, // por unidad
        ]],
    ]))->assertSessionHasNoErrors();

    $cot = Cotizacion::orderByDesc('id')->first();
    expect((float) $cot->subtotal)->toBe(30.0);
    expect((float) $cot->descuento_total)->toBe(6.0);
    expect((float) $cot->total)->toBe(24.0);
});

it('edita una cotización vigente (items + cabecera) y recalcula totales', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();

    $this->put(route('cotizaciones.update', $cot), payloadCotizacion([
        'referencia' => 'Paciente Firulais',
        'items'      => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 12,
            'descuento_item'     => 0,
        ]],
    ]))->assertSessionHasNoErrors();

    $cot->refresh()->load('items');
    expect($cot->numero)->toBe('COT-0001'); // el número no cambia al editar
    expect($cot->referencia)->toBe('Paciente Firulais');
    expect($cot->items)->toHaveCount(1);
    expect((float) $cot->items->first()->cantidad)->toBe(5.0);
    expect((float) $cot->total)->toBe(60.0);
    expect(Auditoria::where('accion', 'cotizacion.editada')->where('modelo_id', $cot->id)->exists())->toBeTrue();
});

it('cambia el estado con auditoría y exige motivo al rechazar/anular', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();

    // Rechazar sin motivo → error de validación
    $this->post(route('cotizaciones.estado', $cot), ['estado' => 'rechazada'])
        ->assertSessionHasErrors('motivo');

    // Aceptar (motivo opcional)
    $this->post(route('cotizaciones.estado', $cot), ['estado' => 'aceptada'])
        ->assertSessionHasNoErrors();
    expect($cot->refresh()->estado)->toBe('aceptada');
    expect(Auditoria::where('accion', 'cotizacion.aceptada')->where('modelo_id', $cot->id)->exists())->toBeTrue();

    // Anular con motivo (queda en la bitácora de seguimiento)
    $this->post(route('cotizaciones.estado', $cot), ['estado' => 'anulada', 'motivo' => 'Se cotizó doble por error'])
        ->assertSessionHasNoErrors();
    $cot->refresh();
    expect($cot->estado)->toBe('anulada');
    expect($cot->notas_seguimiento)->toContain('Se cotizó doble por error');
    expect(Auditoria::where('accion', 'cotizacion.anulada')->where('modelo_id', $cot->id)->exists())->toBeTrue();

    // Una anulada ya no admite transiciones
    $this->post(route('cotizaciones.estado', $cot), ['estado' => 'aceptada'])
        ->assertSessionHasErrors('estado');
});

it('registra contactos de seguimiento (ultimo_contacto + bitácora)', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();

    $this->post(route('cotizaciones.contacto', $cot), [
        'fecha' => now()->toDateString(),
        'nota'  => 'Lo llamé, revisa la propuesta el lunes',
    ])->assertSessionHasNoErrors();

    $cot->refresh();
    expect($cot->ultimo_contacto->toDateString())->toBe(now()->toDateString());
    expect($cot->notas_seguimiento)->toContain('revisa la propuesta el lunes');
    expect(Auditoria::where('accion', 'cotizacion.contacto')->where('modelo_id', $cot->id)->exists())->toBeTrue();
});

it('marca automáticamente como vencidas las vigentes pasadas de fecha al listar', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion([
        'fecha'             => now()->subDays(10)->toDateString(),
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]))->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();
    expect($cot->estado)->toBe('vigente');

    $this->get(route('cotizaciones.index'))->assertOk();

    expect($cot->refresh()->estado)->toBe('vencida');
});

it('el index responde OK con KPIs y catálogos para el formulario', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();

    $this->get(route('cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Ventas/Cotizaciones')
            ->has('cotizaciones.data', 1)
            ->has('kpis.vigentes')
            ->has('kpis.monto_vigente')
            ->has('kpis.por_vencer')
            ->has('kpis.vencidas_sin_respuesta')
            ->has('clientes')
            ->has('productos'));
});

it('prellena el POS desde la cotización (?cotizacion_id=) con los precios congelados', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();

    $this->env->abrirTurno();

    $this->get(route('pos.index', ['cotizacion_id' => $cot->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Index')
            ->where('cotizacionPrellenada.numero', $cot->numero)
            ->where('cotizacionPrellenada.referencia', 'Obra Av. Grau')
            ->where('cotizacionPrellenada.cliente.id', $this->cliente->id)
            ->where('cotizacionPrellenada.items.0.precio_unitario', 10)
            ->where('cotizacionPrellenada.tiene_inactivos', false));
});

it('convierte la cotización en venta vía POS: queda convertida y vinculada', function () {
    $this->post(route('cotizaciones.store'), payloadCotizacion())->assertSessionHasNoErrors();
    $cot = Cotizacion::orderByDesc('id')->first();

    $this->env->abrirTurno();

    $this->post(route('ventas.store'), [
        'cliente_id'       => $this->cliente->id,
        'tipo_comprobante' => 'ticket',
        'cotizacion_id'    => $cot->id,
        'items'            => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10, // precio cotizado honrado
            'incluye_igv'        => true,
        ]],
        'pagos'            => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 20,
        ]],
    ])->assertSessionHasNoErrors();

    $cot->refresh();
    expect($cot->estado)->toBe('convertida');
    expect($cot->venta_id)->not->toBeNull();
    expect((float) $cot->venta->total)->toBe(20.0);
    expect(Auditoria::where('accion', 'cotizacion.convertida')->where('modelo_id', $cot->id)->exists())->toBeTrue();

    // Una convertida ya no puede volver a convertirse ni editarse
    $this->put(route('cotizaciones.update', $cot), payloadCotizacion())->assertStatus(422);
});
