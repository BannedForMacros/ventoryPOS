<?php

use App\Models\Cliente;
use App\Models\Venta;
use Illuminate\Support\Facades\URL;
use Tests\Support\TestEnv;

/**
 * Envío del PDF de la venta por WhatsApp (opcional por empresa).
 *
 *   · apagado: ni el endpoint del botón ni el enlace público responden;
 *   · encendido: el mensaje lleva un enlace FIRMADO al PDF y el teléfono del
 *     cliente con el 51 (lo que exige wa.me);
 *   · el enlace público abre el PDF sin sesión, pero sin firma o con el id
 *     cambiado no; y deja de abrir si la venta se anula.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->env->empresa->update(['venta_whatsapp_pdf' => true]);
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
        'nombres' => 'Rosa', 'apellidos' => 'Quispe', 'telefono' => '987 654 321',
        'es_cliente_general' => false, 'activo' => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'precio_costo' => 20, 'stock_inicial' => 10]);
    $this->env->abrirTurno($this->env->admin);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 2, 'precio_unitario' => 50, 'incluye_igv' => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ])->assertSessionHasNoErrors();

    $this->venta = Venta::withoutGlobalScopes()->where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
});

it('el botón recibe el mensaje con el enlace firmado y el teléfono con 51', function () {
    $r = $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->assertOk();

    expect($r->json('telefono'))->toBe('51987654321')
        ->and($r->json('cliente'))->toBe('Rosa Quispe')
        ->and($r->json('formato'))->toBe('a4')
        ->and($r->json('enlaces.a4.pdf'))->toContain('/venta/' . $this->venta->id . '/pdf')
            ->toContain('formato=a4')->toContain('signature=')
        ->and($r->json('enlaces.ticket.pdf'))->toContain('formato=ticket')
        ->and($r->json('enlaces.a4.mensaje'))->toContain($r->json('enlaces.a4.pdf'))->toContain('S/ 100.00');
});

/** [ancho, alto] en pt de la primera página del PDF. */
function tamanoPdf(string $pdf): array
{
    preg_match('/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m);

    return [(float) ($m[1] ?? 0), (float) ($m[2] ?? 0)];
}

it('el enlace firmado abre el PDF sin sesión', function () {
    $url = $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->json('enlaces.a4.pdf');
    auth()->logout();

    $r = $this->get($url)->assertOk();
    expect($r->headers->get('content-type'))->toContain('application/pdf');
});

it('el PDF sale en A4 o en ticket de 80 mm del largo justo', function () {
    $enlaces = $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->json('enlaces');
    auth()->logout();

    [$w, $h] = tamanoPdf($this->get($enlaces['a4']['pdf'])->assertOk()->getContent());
    expect(round($w))->toBe(595.0)->and(round($h))->toBe(842.0);

    [$w, $h] = tamanoPdf($this->get($enlaces['ticket']['pdf'])->assertOk()->getContent());
    expect(round($w))->toBe(227.0)->and($h)->toBeGreaterThan(150)->toBeLessThan(1000);
});

it('el formato por defecto es el de la empresa y no se cambia sin romper la firma', function () {
    $this->env->empresa->update(['venta_pdf_formato' => 'ticket']);
    $r = $this->getJson(route('ventas.whatsapp-pdf', $this->venta));
    expect($r->json('formato'))->toBe('ticket');

    auth()->logout();
    $this->get(str_replace('formato=ticket', 'formato=a4', $r->json('enlaces.ticket.pdf')))->assertForbidden();
});

it('el PDF del cliente no muestra datos internos', function () {
    $html = view('pdf.venta-cliente-a4', app(\App\Services\VentaPdfCliente::class)->datos($this->venta->fresh()))->render();

    expect($html)->toContain('Rosa Quispe')->toContain('S/ 100.00')
        ->not->toContain('ID interno')->not->toContain('Autorizado por')->not->toContain('SON: SON:');
});

it('sin firma, con la firma alterada o con otro id no abre', function () {
    auth()->logout();
    $this->get('/venta/' . $this->venta->id . '/pdf')->assertForbidden();

    $url = URL::temporarySignedRoute('venta.publica.pdf', now()->addDay(), ['venta' => $this->venta->id]);
    $otro = str_replace('/venta/' . $this->venta->id . '/', '/venta/' . ($this->venta->id + 1) . '/', $url);
    expect($this->get($otro)->status())->toBeIn([403, 404]);   // 404 si ese id no existe
    $this->get($url . 'x')->assertForbidden();                 // firma alterada
});

it('con la opción apagada no hay botón ni enlace', function () {
    $url = $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->json('enlaces.a4.pdf');
    $this->env->empresa->update(['venta_whatsapp_pdf' => false]);

    $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->assertForbidden();
    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('una venta anulada deja de abrir', function () {
    $url = $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->json('enlaces.a4.pdf');
    $this->venta->update(['estado' => 'anulada']);

    $this->getJson(route('ventas.whatsapp-pdf', $this->venta))->assertStatus(422);
    auth()->logout();
    $this->get($url)->assertNotFound();
});

it('la empresa puede guardar la opción', function () {
    expect((new \App\Http\Requests\Configuracion\EmpresaRequest())->rules())
        ->toHaveKey('venta_whatsapp_pdf')->toHaveKey('venta_pdf_formato');
});
