<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosProveedoresSeeder extends Seeder
{
    public function run(): void
    {
        Modulo::updateOrCreate(
            ['slug' => 'proveedores'],
            [
                'padre_id' => null,
                'nombre'   => 'Proveedores',
                'icono'    => 'Truck',
                'ruta'     => '/proveedores',
                'orden'    => 35,
                'activo'   => true,
            ]
        );
    }
}
