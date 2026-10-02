<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tiempo real: "en esta empresa cambió X". No lleva datos: cada pantalla
 * abierta que muestra X vuelve a pedirlo al servidor, así nunca enseña algo
 * distinto a la base. Se envía en el acto (sin cola) para que llegue al
 * instante; TiempoReal lo protege para que jamás frene una operación.
 */
class Cambio implements ShouldBroadcastNow
{
    /** @param string[] $recursos p. ej. ['ventas', 'stock'] */
    public function __construct(public int $empresaId, public array $recursos) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('empresa.' . $this->empresaId);
    }

    public function broadcastAs(): string
    {
        return 'cambio';
    }

    public function broadcastWith(): array
    {
        return ['recursos' => array_values($this->recursos)];
    }
}
