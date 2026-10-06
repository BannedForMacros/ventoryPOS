<?php

use Tests\Support\TestEnv;

/**
 * Humo: cada pantalla principal abre sin error de servidor para un admin con
 * datos mínimos (un producto y un turno abierto). No prueba reglas de negocio:
 * atrapa los 500 que se cuelan cuando cambia lo que una pantalla necesita.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->env->crearProducto();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

it('abre sin error la pantalla :dataset', function (string $ruta) {
    $this->get($ruta)->assertOk();
})->with([
    '/dashboard', '/pos', '/ventas', '/cotizaciones', '/devoluciones', '/clientes', '/proveedores',
    '/turnos', '/gastos',
    '/inventario/stock', '/inventario/entradas', '/inventario/entradas/crear', '/inventario/salidas',
    '/inventario/ajustes', '/inventario/cierres',
    '/catalogo/productos', '/catalogo/categorias',
    '/finanzas/balance', '/finanzas/tesoreria', '/finanzas/consolidacion', '/finanzas/estado-cuenta',
    '/finanzas/cuentas-por-cobrar', '/finanzas/cuentas-por-pagar', '/finanzas/anticipos',
    '/finanzas/adelantos', '/finanzas/deudas', '/finanzas/descuentos-planilla',
    '/reportes/ventas', '/reportes/utilidad', '/reportes/productos', '/reportes/caja', '/reportes/gastos',
    '/reportes/devoluciones', '/reportes/kardex', '/reportes/cierre-mes', '/reportes/auditoria',
    '/configuracion/empresas', '/configuracion/locales', '/configuracion/cajas', '/configuracion/usuarios',
    '/configuracion/roles', '/configuracion/cuentas', '/configuracion/metodos-pago',
]);

it('abre el cierre del turno', function () {
    $this->get(route('turnos.cerrar.page', $this->turno))->assertOk();
});

it('abre transferencias en una empresa con almacén central y local', function () {
    $this->env->empresa->update(['modo_almacen' => 'central_y_local']);
    $this->get('/inventario/transferencias')->assertOk();
    $this->get('/inventario/transferencias/crear')->assertOk();
});
