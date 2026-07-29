<?php

use App\Models\VentaComprobante;
use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;

/**
 * Las listas de estados del POS derivan del enum COMPARTIDO, no de una copia local.
 *
 * ─── Por qué existe este archivo ─────────────────────────────────────────────
 *
 * Tres bugs fiscales seguidos tuvieron la misma causa: una lista de estados escrita
 * a mano en el POS que no coincidía con la del emisor.
 *
 *   1. Faltaban `enviado`, `en_resumen` y `pendiente_anulacion` → se podía anular
 *      localmente una venta que SUNAT ya conocía.
 *   2. Se pedía nota de crédito sobre boletas en `pendiente_resumen`, que el emisor
 *      rechaza → ninguna devolución de boleta generaba jamás su NC.
 *   3. Faltaba `anulado` → tras aceptar la nota de crédito el emisor deja el
 *      original en `anulado`, el polling lo copiaba y `esEmitido()` pasaba a ser
 *      false. Es decir: se podía anular o EDITAR en el POS una venta cuya factura
 *      Y cuya nota de crédito ya estaban en SUNAT. Doble reversión de stock y de
 *      caja, y una venta editada cuyos importes ya no se parecen a los dos
 *      documentos fiscales emitidos.
 *
 * Los tests de aquí fallan si alguien vuelve a desalinear las listas.
 */

it('anulado bloquea anular y editar: está en la lista de estados emitidos', function () {
    // La comprobación que habría cazado el defecto nº 3. Si se quita `anulado` de
    // EstadoComprobante::bloqueaAnulacion() (o de la constante espejo), esto falla.
    expect(VentaComprobante::estadosEmitidos())->toContain('anulado');
    expect(VentaComprobante::ESTADOS_EMITIDOS)->toContain('anulado');

    expect(EstadoComprobante::ANULADO->bloqueaAnulacion())->toBeTrue();
});

it('la lista de estados que bloquean se toma del enum compartido, caso por caso', function () {
    $emitidos = VentaComprobante::estadosEmitidos();

    // Se recorre el enum ENTERO: así, si mañana el contrato añade un estado, este
    // test obliga a decidir explícitamente de qué lado cae en vez de olvidarlo.
    foreach (EstadoComprobante::cases() as $caso) {
        expect(in_array($caso->value, $emitidos, true))
            ->toBe(
                $caso->bloqueaAnulacion(),
                "El estado «{$caso->value}» no coincide con EstadoComprobante::bloqueaAnulacion().",
            );
    }

    // La tabla §9 del contrato, comprobada a mano por si alguien "simplifica" el
    // enum sin darse cuenta de lo que significa cada estado.
    expect($emitidos)->toContain('enviando', 'enviado', 'pendiente_resumen',
        'en_resumen', 'pendiente_anulacion', 'aceptado', 'anulado');

    // Los que NO bloquean: nada llegó a SUNAT, la venta se puede revertir sin NC.
    expect($emitidos)->not->toContain('pendiente');
    expect($emitidos)->not->toContain('rechazado');
    expect($emitidos)->not->toContain('error_envio');
    expect($emitidos)->not->toContain('simulado');
});

it('los dos estados propios del POS se tratan explícitamente', function () {
    // `error_mapeo` y `no_emitido` no existen en el emisor porque describen algo
    // que pasa ANTES de llamarle. Ninguno bloquea (SUNAT no sabe nada de esa
    // venta), los dos son terminales (esperar no los mueve) y ninguno es
    // reintentable ni acreditable.
    foreach (VentaComprobante::ESTADOS_SOLO_POS as $estado) {
        expect(VentaComprobante::estadosEmitidos())->not->toContain($estado);
        expect(VentaComprobante::estadosTerminales())->toContain($estado);
        expect(VentaComprobante::estadosReintentables())->not->toContain($estado);
        expect(VentaComprobante::estadosAcreditables())->not->toContain($estado);
    }

    // Y no se cuelan en el enum compartido, que es de FacturaMac.
    $delEnum = array_map(fn (EstadoComprobante $c) => $c->value, EstadoComprobante::cases());
    expect($delEnum)->not->toContain('error_mapeo');
    expect($delEnum)->not->toContain('no_emitido');
});

it('las constantes espejo no se han desalineado de sus métodos derivados', function () {
    // Las constantes solo sobreviven porque PHP no admite llamadas a métodos en una
    // expresión constante y hay consumidores que necesitan una constante de verdad
    // (ConsultarEstadoComprobante, EmitirNotaCreditoElectronica). Este test es lo
    // que impide que vuelvan a divergir del enum. Se comparan como CONJUNTOS: el
    // orden no significa nada.
    $comoConjunto = function (array $lista): array {
        sort($lista);

        return $lista;
    };

    expect($comoConjunto(VentaComprobante::ESTADOS_EMITIDOS))
        ->toBe($comoConjunto(VentaComprobante::estadosEmitidos()));

    expect($comoConjunto(VentaComprobante::ESTADOS_ACREDITABLES))
        ->toBe($comoConjunto(VentaComprobante::estadosAcreditables()));

    expect($comoConjunto(VentaComprobante::ESTADOS_REINTENTABLES))
        ->toBe($comoConjunto(VentaComprobante::estadosReintentables()));

    expect($comoConjunto(VentaComprobante::ESTADOS_TERMINALES))
        ->toBe($comoConjunto(VentaComprobante::estadosTerminales()));
});

it('acreditables y reintentables siguen al enum, no a una copia', function () {
    foreach (EstadoComprobante::cases() as $caso) {
        expect(in_array($caso->value, VentaComprobante::estadosAcreditables(), true))
            ->toBe($caso->admiteNotaCredito(), "admiteNotaCredito difiere en «{$caso->value}»");

        expect(in_array($caso->value, VentaComprobante::estadosReintentables(), true))
            ->toBe($caso->esReintentable(), "esReintentable difiere en «{$caso->value}»");
    }

    // Una boleta en `pendiente_resumen` todavía no existe para SUNAT: la NC hay
    // que ESPERARLA (§6.2), no fallarla.
    expect(VentaComprobante::estadosAcreditables())->not->toContain('pendiente_resumen');
    expect(EstadoComprobante::PENDIENTE_RESUMEN->debeEsperar())->toBeTrue();
});
