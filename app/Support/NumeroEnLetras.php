<?php

namespace App\Support;

/**
 * Convierte un importe a su expresión en letras al estilo de los comprobantes
 * peruanos: "SON: SETENTA CON 00/100 SOLES". Cubre hasta miles de millones,
 * suficiente para cualquier venta real del POS.
 */
class NumeroEnLetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS',
        'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
    ];

    private const DECENAS = [
        '', '', 'VEINTI', 'TREINTA', 'CUARENTA', 'CINCUENTA',
        'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private const CENTENAS = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    /** "SON: SETENTA CON 00/100 SOLES" (o la moneda que se pase). */
    public static function importe(float $monto, string $moneda = 'PEN'): string
    {
        $entero    = (int) floor(abs($monto));
        $centavos  = (int) round((abs($monto) - $entero) * 100);
        if ($centavos === 100) { // redondeo de .999…
            $entero++;
            $centavos = 0;
        }

        $nombreMoneda = match (strtoupper($moneda)) {
            'USD'   => 'DÓLARES AMERICANOS',
            default => 'SOLES',
        };

        $letras = $entero === 0 ? 'CERO' : self::numero($entero);

        return sprintf('SON: %s CON %02d/100 %s', $letras, $centavos, $nombreMoneda);
    }

    private static function numero(int $n): string
    {
        if ($n >= 1_000_000_000) {
            $miles = intdiv($n, 1_000_000_000);
            $resto = $n % 1_000_000_000;
            $txt   = ($miles === 1 ? 'MIL' : self::numero($miles) . ' MIL') . ' MILLONES';
            return trim($txt . ($resto > 0 ? ' ' . self::numero($resto) : ''));
        }
        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto    = $n % 1_000_000;
            $txt      = $millones === 1 ? 'UN MILLÓN' : self::numero($millones) . ' MILLONES';
            return trim($txt . ($resto > 0 ? ' ' . self::numero($resto) : ''));
        }
        if ($n >= 1_000) {
            $miles = intdiv($n, 1_000);
            $resto = $n % 1_000;
            $txt   = $miles === 1 ? 'MIL' : self::numero($miles) . ' MIL';
            return trim($txt . ($resto > 0 ? ' ' . self::numero($resto) : ''));
        }
        if ($n >= 100) {
            if ($n === 100) {
                return 'CIEN';
            }
            $resto = $n % 100;
            return trim(self::CENTENAS[intdiv($n, 100)] . ($resto > 0 ? ' ' . self::numero($resto) : ''));
        }
        if ($n <= 20) {
            return self::UNIDADES[$n];
        }
        $dec = intdiv($n, 10);
        $uni = $n % 10;
        if ($dec === 2) { // veintiuno…veintinueve, en una sola palabra
            return $uni === 0 ? 'VEINTE' : 'VEINTI' . self::pegadoVeinti($uni);
        }
        return self::DECENAS[$dec] . ($uni > 0 ? ' Y ' . self::UNIDADES[$uni] : '');
    }

    /** VEINTIDÓS/VEINTITRÉS/VEINTISÉIS llevan tilde al pegarse. */
    private static function pegadoVeinti(int $uni): string
    {
        return match ($uni) {
            2 => 'DÓS',
            3 => 'TRÉS',
            6 => 'SÉIS',
            default => self::UNIDADES[$uni],
        };
    }
}
