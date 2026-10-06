<?php

use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\DeudaPago;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\PlanillaDescuento;
use App\Models\Proveedor;
use App\Models\ProveedorAdelanto;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Services\ProveedorAdelantoService;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestEnv;

/**
 * Revisión de finanzas — deudas, adelantos a proveedores y planilla. Cada
 * prueba fallaba antes de su arreglo.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->efectivo = $this->env->metodo('efectivo')->id;
});

function rfdDeuda($test, string $direccion, float $monto): Deuda
{
    $test->post(route('finanzas.deudas.store'), [
        'direccion'      => $direccion,
        'tipo'           => 'personal',
        'nombre'         => 'Deuda ' . uniqid(),
        'monto_original' => $monto,
        'fecha_inicio'   => now()->toDateString(),
        'registrar_caja' => false,
        'metodo_pago_id' => $test->efectivo,
    ])->assertSessionHasNoErrors();

    return Deuda::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();
}

function rfdCompensar($test, Deuda $porPagar, Deuda $porCobrar, float $monto): void
{
    $test->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => $monto,
    ])->assertSessionHasNoErrors();
}

function rfdProveedor($test): Proveedor
{
    return Proveedor::create([
        'empresa_id'       => $test->env->empresa->id,
        'tipo_documento'   => 'RUC',
        'numero_documento' => '20' . random_int(100000000, 999999999),
        'razon_social'     => 'Proveedor ' . uniqid(),
        'activo'           => true,
    ]);
}

it('#7 editar una amortización respeta lo ya compensado (no deja pagar de más)', function () {
    $porPagar  = rfdDeuda($this, 'por_pagar', 100);
    $porCobrar = rfdDeuda($this, 'por_cobrar', 100);
    rfdCompensar($this, $porPagar, $porCobrar, 40);

    $this->post(route('finanzas.deudas.pago', $porPagar), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 30, 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasNoErrors();
    $cuota = DeudaPago::where('deuda_id', $porPagar->id)->where('tipo', 'amortizacion')->firstOrFail();

    // Quedan 100 − 40 compensado = 60 para cuotas: 70 es pagar de más.
    $this->put(route('finanzas.deudas.pagos.update', $cuota), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 70, 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasErrors('monto');

    expect((float) $cuota->fresh()->monto)->toBe(30.0);
});

it('#8 eliminar una deuda con compensación se bloquea: el hermano no queda huérfano', function () {
    $porPagar  = rfdDeuda($this, 'por_pagar', 100);
    $porCobrar = rfdDeuda($this, 'por_cobrar', 100);
    rfdCompensar($this, $porPagar, $porCobrar, 40);

    $this->delete(route('finanzas.deudas.destroy', $porPagar), ['motivo' => 'registro duplicado'])->assertSessionHasErrors();

    expect(Deuda::find($porPagar->id))->not->toBeNull();
    expect((float) $porCobrar->fresh()->saldo)->toBe(60.0);
    expect(DeudaPago::where('deuda_id', $porCobrar->id)->where('tipo', 'compensacion')->count())->toBe(1);
});

it('#10 editar un adelanto a Efectivo mueve su cuenta y su egreso a la caja de efectivo', function () {
    $proveedor = rfdProveedor($this);
    $transferencia = $this->env->metodo('transferencia')->id;
    $banco = Cuenta::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'BCP Soles', 'es_efectivo' => false, 'activo' => true])->id;
    \Illuminate\Support\Facades\DB::table('cuenta_metodo_pago')->insert(['cuenta_id' => $banco, 'metodo_pago_id' => $transferencia]);

    $this->post(route('finanzas.adelantos.store'), [
        'proveedor_id' => $proveedor->id, 'fecha' => now()->toDateString(), 'monto' => 200,
        'metodo_pago_id' => $transferencia, 'cuenta_id' => $banco,
    ])->assertSessionHasNoErrors();
    $adelanto = ProveedorAdelanto::where('proveedor_id', $proveedor->id)->firstOrFail();
    expect($adelanto->cuenta_id)->toBe($banco);

    $this->put(route('finanzas.adelantos.update', $adelanto), [
        'monto' => 200, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasNoErrors();

    $cajaEfectivo = Cuenta::where('empresa_id', $this->env->empresa->id)->where('es_efectivo', true)->value('id');
    expect($cajaEfectivo)->not->toBe($banco);
    expect($adelanto->fresh()->cuenta_id)->toBe($cajaEfectivo);
    expect(CuentaMovimiento::where('ref_tipo', 'proveedor_adelanto')->where('ref_id', $adelanto->id)->value('cuenta_id'))->toBe($cajaEfectivo);
});

it('#6b revertir un pago con adelanto ya devuelto no le resucita el saldo', function () {
    $proveedor = rfdProveedor($this);
    $entrada = Entrada::create([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->env->almacen->id, 'user_id' => $this->env->admin->id,
        'proveedor_id' => $proveedor->id, 'proveedor' => $proveedor->razon_social, 'fecha' => now()->toDateString(),
        'total' => 100, 'monto_pagado' => 0, 'estado' => 'confirmado', 'estado_pago' => 'pendiente',
    ]);
    $adelanto = ProveedorAdelanto::create([
        'empresa_id' => $this->env->empresa->id, 'proveedor_id' => $proveedor->id, 'user_id' => $this->env->admin->id,
        'metodo_pago_id' => $this->efectivo, 'fecha' => now()->toDateString(), 'monto' => 100, 'saldo' => 100, 'estado' => 'activo',
    ]);
    $pago = app(ProveedorAdelantoService::class)->aplicar($adelanto->id, 60, $entrada, $this->env->admin, now()->toDateString());
    $adelanto->update(['estado' => 'devuelto']); // el proveedor devolvió los 40 restantes

    expect(fn () => app(ProveedorAdelantoService::class)->revertirAplicacion($pago))->toThrow(ValidationException::class);
    expect($adelanto->fresh()->estado)->toBe('devuelto');
    expect((float) $adelanto->fresh()->saldo)->toBe(40.0);
});

it('#12 bordes: no se edita una compensación como cuota, ni un abono de venta anulada, ni se aplica planilla con fecha futura', function () {
    // Compensación entre deudas: no se edita.
    $porPagar  = rfdDeuda($this, 'por_pagar', 100);
    $porCobrar = rfdDeuda($this, 'por_cobrar', 100);
    rfdCompensar($this, $porPagar, $porCobrar, 40);
    $comp = DeudaPago::where('deuda_id', $porPagar->id)->where('tipo', 'compensacion')->firstOrFail();
    $this->put(route('finanzas.deudas.pagos.update', $comp), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 40, 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasErrors();
    expect($comp->fresh()->tipo)->toBe('compensacion');
    expect(CuentaMovimiento::where('ref_tipo', 'deuda_pago')->where('ref_id', $comp->id)->count())->toBe(0);

    // Abono de una venta anulada: no se toca por separado.
    $venta = Venta::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'turno_id' => $this->env->abrirTurno()->id,
        'caja_id' => $this->env->caja->id, 'user_id' => $this->env->admin->id, 'cliente_id' => $this->env->clienteGeneral->id,
        'numero' => 'V-' . random_int(10000, 99999), 'tipo_comprobante' => 'ticket', 'subtotal' => 100, 'descuento_total' => 0,
        'igv' => 0, 'total' => 100, 'estado' => 'completada', 'es_credito' => true, 'monto_pagado' => 0, 'saldo_pendiente' => 100,
        'fecha_venta' => now(),
    ]);
    $this->post(route('finanzas.cxc.abonar', $venta->id), ['monto' => 30, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasNoErrors();
    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    $venta->update(['estado' => 'anulada']);
    $this->put(route('finanzas.cxc.abonos.update', $abono), ['monto' => 50, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasErrors();
    expect((float) $abono->fresh()->monto)->toBe(30.0);

    // Planilla: la fecha de aplicación no puede ser futura.
    $desc = PlanillaDescuento::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id, 'registrado_por' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 20, 'motivo' => 'Faltante de caja', 'estado' => 'pendiente',
    ]);
    $this->post(route('finanzas.planilla-descuentos.aplicar', $desc), ['fecha_aplicacion' => now()->addDays(5)->toDateString()])
        ->assertSessionHasErrors('fecha_aplicacion');
    expect($desc->fresh()->estado)->toBe('pendiente');
});
