<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'ruc',
        'direccion',
        'telefono',
        'email',
        'logo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function locales(): HasMany
    {
        return $this->hasMany(Local::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
