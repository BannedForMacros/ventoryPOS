<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'es_admin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_admin' => 'boolean',
            'activo'   => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class)->with('modulo');
    }
}
