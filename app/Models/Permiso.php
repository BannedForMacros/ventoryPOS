<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    protected $fillable = [
        'rol_id',
        'modulo_id',
        'ver',
        'crear',
        'editar',
        'eliminar',
    ];

    protected function casts(): array
    {
        return [
            'ver'      => 'boolean',
            'crear'    => 'boolean',
            'editar'   => 'boolean',
            'eliminar' => 'boolean',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }
}
