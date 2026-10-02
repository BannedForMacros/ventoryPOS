<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Qué fechas puede llevar un comprobante electrónico.
 *
 * SUNAT acepta un comprobante si se envía dentro de los 3 días calendario
 * siguientes a su fecha de emisión. Más atrás lo rechaza, y FacturaMac lo corta
 * antes de numerarlo (`datos_invalidos`): la venta quedaría cobrada y SIN
 * comprobante. Por eso el POS lo valida ANTES de cobrar, con la misma ventana.
 *
 * Dos caminos llegan a una fecha distinta de hoy:
 *   · el selector de fecha de la factura (empresas.pos_fecha_emision_factura);
 *   · un admin vendiendo en un turno REABIERTO de otro día (la fecha del turno).
 */
final class VentanaEmisionSunat
{
    public const DIAS_HACIA_ATRAS = 3;

    public static function minima(): Carbon
    {
        return Carbon::today()->subDays(self::DIAS_HACIA_ATRAS);
    }

    public static function maxima(): Carbon
    {
        return Carbon::today();
    }

    /** ¿SUNAT acepta un comprobante con esta fecha de emisión (Y-m-d)? */
    public static function admite(string $fecha): bool
    {
        $f = Carbon::parse($fecha)->startOfDay();

        return $f->betweenIncluded(self::minima(), self::maxima());
    }

    public static function mensaje(): string
    {
        return 'SUNAT solo acepta comprobantes con fecha de hoy y hasta ' . self::DIAS_HACIA_ATRAS
            . ' días atrás (desde el ' . self::minima()->format('d/m/Y') . '). '
            . 'Elige otra fecha o registra la venta como ticket.';
    }
}
