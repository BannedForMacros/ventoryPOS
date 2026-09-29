<?php

namespace App\Support;

use App\Models\Empresa;

/**
 * Configuración de Entregas de una empresa (empresas.usa_entregas +
 * empresas.entrega_config), con sus valores por defecto.
 *
 * La función es opcional: una empresa que no reparte la deja apagada y su
 * venta, su stock y su ticket quedan exactamente como siempre.
 */
class ConfigEntregas
{
    public const RECOJO = 'recojo';
    public const ENVIO  = 'envio';

    public const DEFECTO = [
        // Aviso "¿recoge en tienda o es envío?" si la venta supera este monto y
        // sigue marcada como recojo. null = sin aviso.
        'aviso_monto'          => 500.0,
        // Qué se exige al registrar un envío.
        'ruta_obligatoria'     => true,
        'fecha_obligatoria'    => true,
        // La mercadería de un envío sale del stock recién al entregarse (queda
        // pendiente de despacho). Apagado: sale al vender, como en un recojo.
        'envio_sale_al_entregar' => true,
    ];

    /**
     * Cómo llama cada negocio a sus entregas ("Envío a obra", "Delivery"…).
     * Salen en los botones de la venta, en el ticket y en el de despacho, con
     * cualquier plantilla de ticket.
     */
    public const TEXTOS = [
        'recojo'          => ['nombre' => 'Cuando el cliente recoge',      'defecto' => 'RECOJO EN TIENDA',       'max' => 30],
        'envio'           => ['nombre' => 'Cuando se le envía',            'defecto' => 'ENVÍO A DOMICILIO',      'max' => 30],
        'cobrar_entrega'  => ['nombre' => 'Envío con saldo por cobrar',    'defecto' => 'COBRAR AL ENTREGAR',     'max' => 40],
        'titulo_despacho' => ['nombre' => 'Título del ticket de despacho', 'defecto' => 'DESPACHO DE MERCADERÍA', 'max' => 40],
    ];

    /** @return array{activo:bool, aviso_monto:?float, ruta_obligatoria:bool, fecha_obligatoria:bool, envio_sale_al_entregar:bool, textos:array<string,string>} */
    public static function de(?Empresa $empresa): array
    {
        $g = is_array($empresa?->entrega_config) ? $empresa->entrega_config : [];
        // Antes estos textos se guardaban con la plantilla del ticket: se respetan.
        $previos = is_array($empresa?->ticket_plantilla) ? (array) ($empresa->ticket_plantilla['textos'] ?? []) : [];

        return [
            'activo'                 => (bool) ($empresa?->usa_entregas ?? false),
            'aviso_monto'            => array_key_exists('aviso_monto', $g)
                ? ($g['aviso_monto'] === null || (float) $g['aviso_monto'] <= 0 ? null : round((float) $g['aviso_monto'], 2))
                : self::DEFECTO['aviso_monto'],
            'ruta_obligatoria'       => (bool) ($g['ruta_obligatoria'] ?? self::DEFECTO['ruta_obligatoria']),
            'fecha_obligatoria'      => (bool) ($g['fecha_obligatoria'] ?? self::DEFECTO['fecha_obligatoria']),
            'envio_sale_al_entregar' => (bool) ($g['envio_sale_al_entregar'] ?? self::DEFECTO['envio_sale_al_entregar']),
            'textos'                 => self::textos((array) ($g['textos'] ?? []) + $previos),
        ];
    }

    /** Textos completos: vacío o ausente = el texto por defecto. */
    public static function textos(array $guardados): array
    {
        $res = [];
        foreach (self::TEXTOS as $clave => $def) {
            $v = is_string($guardados[$clave] ?? null) ? mb_substr(trim($guardados[$clave]), 0, $def['max']) : '';
            $res[$clave] = $v !== '' ? $v : $def['defecto'];
        }

        return $res;
    }

    public static function catalogoTextos(): array
    {
        return array_map(fn ($clave, $def) => ['clave' => $clave] + $def, array_keys(self::TEXTOS), self::TEXTOS);
    }
}
