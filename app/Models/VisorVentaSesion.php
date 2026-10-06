<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una foto del cuaderno leída por el visor de ventas (cuenta para el límite diario). */
class VisorVentaSesion extends Model
{
    protected $table = 'visor_ventas_sesiones';

    protected $fillable = [
        'empresa_id', 'user_id', 'estado', 'ventas_leidas',
        'modelo', 'tokens_entrada', 'tokens_salida', 'error', 'foto_hash', 'lectura', 'origen_id', 'cruce',
    ];

    protected $casts = ['lectura' => 'array', 'cruce' => 'array'];
}
