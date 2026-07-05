<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaDescuento extends Model
{
    protected $table = 'planilla_descuentos';

    protected $fillable = [
        'empresa_id', 'user_id', 'registrado_por', 'fecha', 'monto', 'motivo',
        'ref_tipo', 'ref_id', 'estado', 'aplicado_por', 'fecha_aplicacion', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'            => 'date:Y-m-d',
            'fecha_aplicacion' => 'date:Y-m-d',
            'monto'            => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo       { return $this->belongsTo(Empresa::class); }
    public function trabajador(): BelongsTo    { return $this->belongsTo(User::class, 'user_id'); }
    public function registradoPor(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }
    public function aplicadoPor(): BelongsTo   { return $this->belongsTo(User::class, 'aplicado_por'); }

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
    public function scopePendiente(Builder $q): Builder          { return $q->where('estado', 'pendiente'); }
}
