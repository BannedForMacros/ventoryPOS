<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Venta del cuaderno ya cobrada por el visor: sirve para avisar si se intenta cargar otra vez. */
class VisorVentaCobrada extends Model
{
    protected $table = 'visor_ventas_cobradas';

    protected $fillable = [
        'empresa_id', 'venta_id', 'user_id', 'fecha_cuaderno', 'total_cuaderno',
        'huella_texto', 'huella_productos', 'sesion_id', 'indice',
    ];
}
