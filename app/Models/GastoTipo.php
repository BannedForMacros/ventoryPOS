<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GastoTipo extends Model
{
    protected $table = 'gasto_tipos';

    protected $fillable = ['empresa_id', 'nombre', 'categoria', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo    { return $this->belongsTo(Empresa::class); }
    public function conceptos(): HasMany    { return $this->hasMany(GastoConcepto::class); }
    public function gastos(): HasMany       { return $this->hasMany(Gasto::class); }

    public function scopeActivo($q)         { return $q->where('activo', true); }
    public function scopeDeEmpresa($q, $id) { return $q->where('empresa_id', $id); }
}
