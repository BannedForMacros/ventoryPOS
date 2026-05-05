<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosSalidasSeeder extends Seeder
{
    public function run(): void
    {
        $inventario    = Modulo::where('slug', 'inventario')->firstOrFail();
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::updateOrCreate(
            ['slug' => 'inventario.salidas'],
            [
                'padre_id' => $inventario->id,
                'nombre'   => 'Salidas',
                'icono'    => 'ArrowUpCircle',
                'ruta'     => '/inventario/salidas',
                'orden'    => 5,
                'activo'   => true,
            ]
        );

        Modulo::updateOrCreate(
            ['slug' => 'configuracion.salidas-tipos'],
            [
                'padre_id' => $configuracion->id,
                'nombre'   => 'Tipos de salida',
                'icono'    => 'Tags',
                'ruta'     => '/configuracion/salidas-tipos',
                'orden'    => 8,
                'activo'   => true,
            ]
        );
    }
}
