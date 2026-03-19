<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $fillable = [
        'empresa_id', 'local_id', 'nombre',
        'caja_chica_activa', 'caja_chica_monto_sugerido', 'caja_chica_en_arqueo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'caja_chica_activa'    => 'boolean',
            'caja_chica_en_arqueo' => 'boolean',
            'activo'               => 'boolean',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo   { return $this->belongsTo(Local::class); }
    public function turnos(): HasMany    { return $this->hasMany(Turno::class); }

    public function scopeActivo($q)            { return $q->where('activo', true); }
    public function scopeDeEmpresa($q, $id)    { return $q->where('empresa_id', $id); }
    public function scopeDeLocal($q, $localId) { return $q->where('local_id', $localId); }

    public function tieneTurnoAbierto(): bool
    {
        return $this->turnos()->where('estado', 'abierto')->exists();
    }

    public function usaCajaChica(): bool
    {
        return $this->caja_chica_activa;
    }
}
