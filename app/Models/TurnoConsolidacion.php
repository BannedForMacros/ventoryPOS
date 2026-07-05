<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoConsolidacion extends Model
{
    protected $table = 'turno_consolidaciones';

    protected $fillable = [
        'turno_id', 'empresa_id', 'user_id', 'fecha',
        'efectivo_declarado', 'efectivo_esperado', 'caja_chica',
        'efectivo_contado', 'diferencia_vs_declarado', 'diferencia_vs_esperado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'                   => 'date:Y-m-d',
            'efectivo_declarado'      => 'decimal:2',
            'efectivo_esperado'       => 'decimal:2',
            'caja_chica'              => 'decimal:2',
            'efectivo_contado'        => 'decimal:2',
            'diferencia_vs_declarado' => 'decimal:2',
            'diferencia_vs_esperado'  => 'decimal:2',
        ];
    }

    public function turno(): BelongsTo   { return $this->belongsTo(Turno::class); }
    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(TurnoConsolidacionItem::class); }

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
