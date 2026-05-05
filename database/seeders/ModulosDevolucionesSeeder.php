<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosDevolucionesSeeder extends Seeder
{
    public function run(): void
    {
        Modulo::updateOrCreate(
            ['slug' => 'devoluciones'],
            [
                'padre_id' => null,
                'nombre'   => 'Devoluciones',
                'icono'    => 'Undo2',
                'ruta'     => '/devoluciones',
                'orden'    => 40,
                'activo'   => true,
            ]
        );

        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::updateOrCreate(
            ['slug' => 'configuracion.devolucion-motivos'],
            [
                'padre_id' => $configuracion->id,
                'nombre'   => 'Motivos de devolución',
                'icono'    => 'Tags',
                'ruta'     => '/configuracion/devolucion-motivos',
                'orden'    => 9,
                'activo'   => true,
            ]
        );
    }
}
