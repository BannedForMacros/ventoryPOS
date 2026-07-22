<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arqueo de una cuenta en el balance de un día: cuánto declara el usuario que
 * tiene físicamente vs cuánto dice el sistema, y la diferencia. Cerrar el conteo
 * NO mueve tesorería; generar el ajuste (opcional) asienta la diferencia como
 * movimiento y deja `ajustado=true`.
 */
class BalanceCuentaArqueo extends Model
{
    protected $table = 'balance_cuenta_arqueos';

    protected $fillable = [
        'balance_diario_id', 'cuenta_id', 'saldo_sistema',
        'monto_declarado', 'diferencia', 'ajustado', 'ajuste_mov_id',
    ];

    protected function casts(): array
    {
        return [
            'saldo_sistema'   => 'decimal:2',
            'monto_declarado' => 'decimal:2',
            'diferencia'      => 'decimal:2',
            'ajustado'        => 'boolean',
        ];
    }

    public function balance(): BelongsTo { return $this->belongsTo(BalanceDiario::class, 'balance_diario_id'); }
    public function cuenta(): BelongsTo  { return $this->belongsTo(Cuenta::class); }
}
