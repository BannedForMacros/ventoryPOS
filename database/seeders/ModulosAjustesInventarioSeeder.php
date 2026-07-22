<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Módulo "Ajustes de inventario" (inventario.ajustes): ingreso/salida de stock
 * por ajuste, sin dinero. Da permiso completo a los roles admin. Idempotente.
 */
class ModulosAjustesInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $inventario = Modulo::where('slug', 'inventario')->firstOrFail();

        $modulo = Modulo::updateOrCreate(
            ['slug' => 'inventario.ajustes'],
            [
                'padre_id' => $inventario->id,
                'nombre'   => 'Ajustes de inventario',
                'icono'    => 'SlidersHorizontal',
                'ruta'     => '/inventario/ajustes',
                'orden'    => 6,
                'activo'   => true,
            ]
        );

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulo) {
            Permiso::updateOrCreate(
                ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true],
            );
        });
    }
}
