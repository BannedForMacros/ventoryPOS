<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoConsolidacionItem extends Model
{
    protected $fillable = [
        'turno_consolidacion_id', 'metodo_pago_id', 'cuenta_id',
        'etiqueta', 'declarado', 'esperado', 'contado', 'diferencia',
    ];

    protected function casts(): array
    {
        return [
            'declarado'  => 'decimal:2',
            'esperado'   => 'decimal:2',
            'contado'    => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function consolidacion(): BelongsTo { return $this->belongsTo(TurnoConsolidacion::class, 'turno_consolidacion_id'); }
    public function metodoPago(): BelongsTo    { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo        { return $this->belongsTo(Cuenta::class); }
}
