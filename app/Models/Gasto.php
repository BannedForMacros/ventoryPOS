<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $fillable = [
        'empresa_id', 'local_id', 'user_id', 'turno_id',
        'gasto_tipo_id', 'gasto_concepto_id',
        'monto', 'fecha', 'comentario',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function empresa(): BelongsTo  { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo    { return $this->belongsTo(Local::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function turno(): BelongsTo    { return $this->belongsTo(Turno::class); }
    public function tipo(): BelongsTo     { return $this->belongsTo(GastoTipo::class, 'gasto_tipo_id'); }
    public function concepto(): BelongsTo { return $this->belongsTo(GastoConcepto::class, 'gasto_concepto_id'); }

    public function scopeDelTurno($q, $turnoId) { return $q->where('turno_id', $turnoId); }
    public function scopeAdministrativos($q)    { return $q->whereNull('turno_id'); }
    public function scopeDeEmpresa($q, $id)     { return $q->where('empresa_id', $id); }

    public function esDelTurno(): bool       { return $this->turno_id !== null; }
    public function esAdministrativo(): bool { return $this->turno_id === null; }
}
