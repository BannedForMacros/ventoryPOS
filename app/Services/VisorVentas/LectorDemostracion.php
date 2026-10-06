<?php

namespace App\Services\VisorVentas;

/**
 * Lector de DEMOSTRACIÓN (solo fuera de producción, VISOR_VENTAS_LECTOR=demo):
 * devuelve una página de ejemplo sin llamar a la API, para probar la pantalla
 * de revisión sin gastar créditos.
 */
class LectorDemostracion implements LectorCuaderno
{
    public function leer(string $imagenBase64, string $mediaType): array
    {
        // Fecha de hoy, como la escribiría la boticaria arriba de la columna.
        $hoy = now()->format('d/m/y');
        $v = fn (?string $fecha, ?float $total, array $items) => ['fecha' => $fecha, 'total' => $total, 'items' => array_map(
            fn ($i) => ['cantidad' => $i[0], 'texto' => $i[1], 'interpretacion' => $i[2], 'seguro' => $i[3]], $items,
        )];

        return [
            'ventas' => [
                // Total distinto al catálogo, un producto repetido y uno que no existe.
                $v($hoy, 3.20, [[10, '10 paracetol', 'paracetamol', true], [20, '20 ibupro', 'ibuprofeno', true], [5, '5 paracetamol', 'paracetamol', true], [5, '5 muscular', null, false]]),
                $v($hoy, 12.50, [[1, '1 tubo colgate', 'pasta dental colgate', true]]),
                $v($hoy, 0.50, [[1, '1 guts', null, false]]),
            ],
            'modelo'         => 'demostracion',
            'tokens_entrada' => 0,
            'tokens_salida'  => 0,
        ];
    }
}
