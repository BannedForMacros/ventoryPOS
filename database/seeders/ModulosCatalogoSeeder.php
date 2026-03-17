<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Catálogo',
            'slug'     => 'catalogo',
            'icono'    => 'Package',
            'ruta'     => null,
            'orden'    => 10,
            'activo'   => true,
        ]);

        $hijos = [
            ['slug' => 'catalogo.categorias', 'nombre' => 'Categorías',            'icono' => 'Tag',         'ruta' => '/catalogo/categorias',     'orden' => 1],
            ['slug' => 'catalogo.unidades',   'nombre' => 'Unidades de medida',    'icono' => 'Ruler',       'ruta' => '/catalogo/unidades-medida', 'orden' => 2],
            ['slug' => 'catalogo.productos',  'nombre' => 'Productos y servicios', 'icono' => 'ShoppingBag', 'ruta' => '/catalogo/productos',       'orden' => 3],
        ];

        foreach ($hijos as $hijo) {
            Modulo::create([
                'padre_id' => $catalogo->id,
                'nombre'   => $hijo['nombre'],
                'slug'     => $hijo['slug'],
                'icono'    => $hijo['icono'],
                'ruta'     => $hijo['ruta'],
                'orden'    => $hijo['orden'],
                'activo'   => true,
            ]);
        }
    }
}
