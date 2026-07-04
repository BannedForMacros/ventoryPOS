<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deuda extends Model
{
    public const DIRECCION_POR_PAGAR  = 'por_pagar';
    public const DIRECCION_POR_COBRAR = 'por_cobrar';

    protected $fillable = [
        'empresa_id', 'user_id', 'direccion', 'tipo', 'nombre',
        'monto_original', 'saldo', 'fecha_inicio', 'fecha_vencimiento',
        'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'      => 'date',
            'fecha_vencimiento' => 'date',
            'monto_original'    => 'decimal:2',
            'saldo'             => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function pagos(): HasMany     { return $this->hasMany(DeudaPago::class); }

    public function scopeActiva(Builder $q): Builder             { return $q->where('estado', 'activa'); }
    public function scopePorPagar(Builder $q): Builder           { return $q->where('direccion', self::DIRECCION_POR_PAGAR); }
    public function scopePorCobrar(Builder $q): Builder          { return $q->where('direccion', self::DIRECCION_POR_COBRAR); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
