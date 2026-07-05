<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteAnticipoAplicacion extends Model
{
    protected $table = 'cliente_anticipo_aplicaciones';

    protected $fillable = [
        'cliente_anticipo_id', 'venta_id', 'user_id',
        'fecha', 'monto', 'cantidad', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'    => 'date:Y-m-d',
            'monto'    => 'decimal:2',
            'cantidad' => 'decimal:4',
        ];
    }

    public function anticipo(): BelongsTo { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }
    public function venta(): BelongsTo    { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
}
