<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosInventarioSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo padre: Inventario
        $inventario = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Inventario',
            'slug'     => 'inventario',
            'icono'    => 'Warehouse',
            'ruta'     => null,
            'orden'    => 20,
            'activo'   => true,
        ]);

        $hijos = [
            ['slug' => 'inventario.stock',          'nombre' => 'Stock actual',    'icono' => 'BarChart3',       'ruta' => '/inventario/stock',                'orden' => 1],
            ['slug' => 'inventario.entradas',       'nombre' => 'Entradas',        'icono' => 'ArrowDownCircle', 'ruta' => '/inventario/entradas',             'orden' => 2],
            ['slug' => 'inventario.transferencias', 'nombre' => 'Transferencias',  'icono' => 'ArrowLeftRight',  'ruta' => '/inventario/transferencias',       'orden' => 3],
        ];

        foreach ($hijos as $hijo) {
            Modulo::create([
                'padre_id' => $inventario->id,
                'nombre'   => $hijo['nombre'],
                'slug'     => $hijo['slug'],
                'icono'    => $hijo['icono'],
                'ruta'     => $hijo['ruta'],
                'orden'    => $hijo['orden'],
                'activo'   => true,
            ]);
        }

        // Hijo nuevo bajo Configuración: Almacenes
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Almacenes',
            'slug'     => 'configuracion.almacenes',
            'icono'    => 'Warehouse',
            'ruta'     => '/configuracion/almacenes',
            'orden'    => 7,
            'activo'   => true,
        ]);
    }
}
