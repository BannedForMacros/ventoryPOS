<?php

namespace App\Models\Concerns;

use App\Support\TiempoReal;

/**
 * Al guardar o borrar el modelo, avisa en tiempo real a las pantallas de su
 * empresa que muestran ese recurso. El modelo declara:
 *   protected static function recursosTiempoReal(): array { return ['clientes']; }
 * y, si no tiene empresa_id propio, sobrescribe empresaTiempoReal().
 */
trait AvisaTiempoReal
{
    public static function bootAvisaTiempoReal(): void
    {
        $avisar = fn ($m) => TiempoReal::marcar($m->empresaTiempoReal(), static::recursosTiempoReal(), static::avisaDesdeConsola());
        static::saved($avisar);
        static::deleted($avisar);
    }

    public function empresaTiempoReal(): ?int
    {
        return $this->empresa_id ? (int) $this->empresa_id : null;
    }

    abstract protected static function recursosTiempoReal(): array;

    /** Si también avisa cuando lo cambia la cola o un comando (por defecto no). */
    protected static function avisaDesdeConsola(): bool
    {
        return false;
    }
}
