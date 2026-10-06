<?php

use App\Models\ConexionFacturacion;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * Quién ve una venta ajena (detalle, ticket, PDF y comprobante electrónico),
 * y la guarda "comprobante en cola" que no debe bloquear para siempre.
 */

beforeEach(function () {
    $this->env      = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->service  = app(VentaService::class);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 100]);
    $this->efectivo = $this->env->metodo('efectivo');
    $this->turno    = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function vvaVenta(string $tipo = 'ticket'): Venta
{
    return test()->service->crear([
        'tipo_comprobante' => $tipo,
        'idempotency_key'  => 'vva-' . uniqid('', true),
        'items' => [[
            'producto_id' => test()->producto->id, 'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad' => 2, 'precio_unitario' => 10,
        ]],
        'pagos' => [['metodo_pago_id' => test()->efectivo->id, 'monto' => 20]],
    ], test()->env->admin, test()->turno)->fresh();
}

/** Usuario no admin con los permisos dados: ['modulo' => ['ver' => true, ...]]. */
function vvaUsuario(array $permisos): User
{
    $rol = Rol::create(['empresa_id' => test()->env->empresa->id, 'nombre' => 'Rol ' . uniqid(), 'es_admin' => false, 'activo' => true]);
    foreach ($permisos as $slug => $acciones) {
        Permiso::create(array_merge(
            ['rol_id' => $rol->id, 'modulo_id' => Modulo::where('slug', $slug)->value('id'),
             'ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false],
            $acciones,
        ));
    }

    return User::create([
        'empresa_id' => test()->env->empresa->id, 'local_id' => test()->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Usuario', 'email' => 'vva+' . uniqid() . '@test.com', 'password' => bcrypt('x'),
        'email_verified_at' => now(), 'activo' => true,
    ]);
}

it('la cajera no consulta, descarga, reenvía ni reintenta el comprobante de una venta ajena', function () {
    $ventaAdmin = vvaVenta();
    $cajera = vvaUsuario(['ventas' => ['ver' => true, 'crear' => true, 'editar' => true]]);

    $this->actingAs($cajera);
    $this->getJson(route('ventas.comprobante.estado', $ventaAdmin))->assertForbidden();
    $this->get(route('ventas.comprobante.pdf', $ventaAdmin))->assertForbidden();
    $this->postJson(route('ventas.comprobante.reintentar', $ventaAdmin))->assertForbidden();
    $this->postJson(route('ventas.comprobante.enviar-correo', $ventaAdmin), ['email' => 'otro@test.com'])->assertForbidden();
    $this->get(route('ventas.pdf', $ventaAdmin))->assertForbidden();
});

it('la cajera sí consulta el comprobante de su propia venta', function () {
    $cajera = vvaUsuario(['ventas' => ['ver' => true, 'crear' => true]]);
    $turno  = $this->env->abrirTurno($cajera);
    $propia = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id' => $this->producto->id, 'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->efectivo->id, 'monto' => 10]],
    ], $cajera, $turno);

    $this->actingAs($cajera);
    $this->getJson(route('ventas.comprobante.estado', $propia))->assertOk();
    $this->get(route('ventas.show', $propia))->assertOk();
});

it('un rol de reportes abre la venta ajena que su reporte le enlaza', function () {
    $ventaAdmin = vvaVenta();
    $supervisor = vvaUsuario(['ventas' => ['ver' => true], 'reportes.ventas' => ['ver' => true]]);

    $this->actingAs($supervisor);
    $this->get(route('ventas.show', $ventaAdmin))->assertOk();
    $this->getJson(route('ventas.ticket', $ventaAdmin))->assertOk();
    $this->getJson(route('ventas.comprobante.estado', $ventaAdmin))->assertOk();
});

it('el permiso amplio no salta la empresa ni el local', function () {
    $ventaAdmin = vvaVenta();
    $otroLocal  = \App\Models\Local::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Otro local', 'activo' => true]);
    $supervisor = vvaUsuario(['ventas' => ['ver' => true], 'reportes.kardex' => ['ver' => true]]);
    $supervisor->update(['local_id' => $otroLocal->id]);

    $this->actingAs($supervisor->fresh());
    $this->get(route('ventas.show', $ventaAdmin))->assertForbidden();
});

it('una boleta sin fila de comprobante solo se da por "en cola" los primeros minutos', function () {
    Http::fake(['*/api/v1/configuracion' => Http::response([
        'modo' => 'produccion', 'emision_activa' => true, 'envia_a_sunat' => true,
        'umbral_boleta_identificada' => 700.0,
    ], 200)]);
    Bus::fake(); // el job no corre: la boleta queda sin fila, como con la emisión pausada

    ConexionFacturacion::updateOrCreate(['empresa_id' => $this->env->empresa->id], [
        'token' => 'token-de-prueba', 'ruc_emisor' => $this->env->empresa->ruc,
        'razon_social_emisor' => $this->env->empresa->razon_social,
        'modo' => 'produccion', 'emision_activa' => true, 'conectado_at' => now()->subDays(3),
    ]);

    $venta = vvaVenta('boleta');
    expect($this->service->motivoBloqueoFiscal($venta))->toContain('todavía está en cola');

    // Vendida hace una hora (emisión pausada entonces, o cola caída): el job ya
    // no va a llegar y la venta no puede quedar bloqueada para siempre.
    Venta::whereKey($venta->id)->update(['created_at' => now()->subHour()]);
    expect($this->service->motivoBloqueoFiscal($venta->fresh()))->toBeNull();
});
