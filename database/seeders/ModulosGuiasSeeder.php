<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * El módulo de Guías de remisión y su permiso.
 *
 * Va suelto y no dentro de Inventario a propósito: una guía no es un movimiento de
 * stock, es el documento fiscal que ampara uno. Colgarlo de Inventario invitaría a
 * pensar que despachar y emitir la guía son la misma acción, y no lo son —se puede
 * despachar sin guía, y se puede emitir una guía de algo que no salió de almacén—.
 *
 * Solo se da permiso a los roles administradores. Quien despacha lo tendrá cuando se
 * le conceda expresamente: emitir un documento ante SUNAT no es lo mismo que sacar
 * mercadería del almacén.
 */
class ModulosGuiasSeeder extends Seeder
{
    public function run(): void
    {
        $modulo = Modulo::updateOrCreate(
            ['slug' => 'guias'],
            [
                'padre_id' => null,
                'nombre'   => 'Guías de remisión',
                'icono'    => 'Truck',
                'ruta'     => '/guias',
                // Detrás de Ventas e Inventario: se usa a diario, pero menos que
                // cobrar o que mirar el stock.
                'orden'    => 35,
                'activo'   => true,
            ],
        );

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulo) {
            Permiso::updateOrCreate(
                ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => false],
            );
        });
    }
}
