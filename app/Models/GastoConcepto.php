<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GastoConcepto extends Model
{
    protected $table = 'gasto_conceptos';

    protected $fillable = ['empresa_id', 'gasto_tipo_id', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo      { return $this->belongsTo(Empresa::class); }
    public function tipo(): BelongsTo         { return $this->belongsTo(GastoTipo::class, 'gasto_tipo_id'); }
    public function gastos(): HasMany         { return $this->hasMany(Gasto::class); }

    public function scopeActivo($q)           { return $q->where('activo', true); }
    public function scopeDeEmpresa($q, $id)   { return $q->where('empresa_id', $id); }
    public function scopeDelTipo($q, $tipoId) { return $q->where('gasto_tipo_id', $tipoId); }
}
