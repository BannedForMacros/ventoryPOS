<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosCierresInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $inventario = Modulo::where('slug', 'inventario')->firstOrFail();

        Modulo::updateOrCreate(
            ['slug' => 'inventario.cierres'],
            [
                'padre_id' => $inventario->id,
                'nombre'   => 'Cierres de inventario',
                'icono'    => 'ClipboardCheck',
                'ruta'     => '/inventario/cierres',
                'orden'    => 4,
                'activo'   => true,
            ]
        );
    }
}
