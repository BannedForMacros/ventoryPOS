<?php

use Tests\Support\TestEnv;

/**
 * El POS tiene que recibir los campos con los que él mismo decide si una factura
 * puede emitirse.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * `VentaController::pos()` mandaba los clientes con un SELECT recortado que no
 * incluía `direccion` ni `es_cliente_general`. Pero `validarComprobante()` (en
 * resources/js/lib/comprobanteElectronico.ts) exige exactamente esos dos:
 *
 *     const tieneDireccion = !!(cliente?.direccion ?? '').trim();
 *
 * Al no venir en el payload llegaba `undefined`, así que `tieneDireccion` era
 * SIEMPRE false y el POS bloqueaba TODA factura con «Una factura requiere cliente
 * con RUC y dirección» — incluso con un cliente que tenía RUC y dirección
 * correctos en la base.
 *
 * Lo peor era la ASIMETRÍA: `StoreVentaRequest::validarComprobanteElectronico()`
 * relee el cliente de la base de datos, así que el backend SÍ habría aceptado esa
 * misma venta. La pantalla frenaba algo que el servidor admitía, y sin salida
 * posible para la cajera: no hay nada que corregir en un cliente que ya está
 * completo. El botón se quedaba en «Corrige el comprobante» para siempre.
 *
 * Solo se notaba con clientes YA EXISTENTES: el que se crea desde el modal del POS
 * vuelve como modelo completo, y por eso el flujo de "cliente nuevo" funcionaba.
 *
 * ─── Cómo se comprueba ───────────────────────────────────────────────────────
 *
 * Ahora el POS no carga toda la lista de clientes; recibe `clienteGeneral` como
 * prop y consulta el resto por el endpoint `pos.clientes`. El test protege que
 * ambas fuentes incluyan `direccion` y `es_cliente_general`.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);

    // Sin turno abierto, /pos redirige: la pantalla no existe fuera de un turno.
    $this->env->abrirTurno($this->env->admin);
});

it('el POS recibe direccion y es_cliente_general del cliente general', function () {
    $cliente = App\Models\Cliente::create([
        'empresa_id'         => $this->env->empresa->id,
        'tipo_documento'     => 'RUC',
        'numero_documento'   => '20612792438',
        'razon_social'       => 'SOMAVAP GROUP S.R.L.',
        'direccion'          => 'CAL. MANUEL SEOANE NRO 600 URB. LA VICTORIA',
        'es_cliente_general' => false,
        'activo'             => true,
    ]);

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($cliente) {
            $general = $page->toArray()['props']['clienteGeneral'];

            expect($general)->not->toBeNull('El cliente general no llegó al POS.');

            // El cliente general debe traer la información mínima para validar
            // comprobantes (dirección solo importa para facturas con RUC, no para
            // el general, pero la flag es obligatoria para identificarlo).
            expect($general)->toHaveKeys(['direccion', 'es_cliente_general']);
        });

    // El endpoint de búsqueda de clientes SÍ debe devolver cualquier cliente
    // activo con los campos completos que el POS necesita para validar facturas.
    $this->getJson(route('pos.clientes', ['q' => 'SOMAVAP']))
        ->assertOk()
        ->assertJsonPath('clientes.0.id', $cliente->id)
        ->assertJsonPath('clientes.0.direccion', 'CAL. MANUEL SEOANE NRO 600 URB. LA VICTORIA')
        ->assertJsonPath('clientes.0.es_cliente_general', false);
});

it('el cliente general llega marcado como tal, sin depender del 99999999', function () {
    // `esClienteGeneral()` tenía un respaldo legado que compara el documento con
    // '99999999'. Es un número mágico que deja de valer en cuanto una empresa
    // configura otro cliente genérico; con la flag en el payload ya no se usa.
    $general = App\Models\Cliente::where('empresa_id', $this->env->empresa->id)
        ->where('es_cliente_general', true)
        ->first();

    if (! $general) {
        $general = App\Models\Cliente::create([
            'empresa_id'         => $this->env->empresa->id,
            'tipo_documento'     => 'DNI',
            'numero_documento'   => '99999999',
            'nombres'            => 'Cliente',
            'apellidos'          => 'General',
            'es_cliente_general' => true,
            'activo'             => true,
        ]);
    }

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($general) {
            $fila = $page->toArray()['props']['clienteGeneral'];

            expect($fila)->not->toBeNull()
                ->and($fila['id'])->toBe($general->id)
                ->and($fila['es_cliente_general'])->toBeTrue();
        });
});
