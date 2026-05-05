<?php

namespace Database\Seeders;

use App\Models\DevolucionMotivo;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class DevolucionMotivosInicialSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['nombre' => 'Defecto de fábrica',     'slug' => 'defecto_fabrica',  'afecta' => 'obliga_merma', 'orden' => 10],
            ['nombre' => 'Producto equivocado',    'slug' => 'producto_equivocado', 'afecta' => 'permite',  'orden' => 20],
            ['nombre' => 'Talla/tamaño incorrecto','slug' => 'talla_incorrecta', 'afecta' => 'permite',     'orden' => 30],
            ['nombre' => 'No le gustó al cliente', 'slug' => 'no_gusto',         'afecta' => 'permite',     'orden' => 40],
            ['nombre' => 'Vencido',                'slug' => 'vencido',          'afecta' => 'obliga_merma','orden' => 50],
            ['nombre' => 'Otro',                   'slug' => 'otro',             'afecta' => 'permite',     'orden' => 99],
        ];

        Empresa::query()->each(function (Empresa $empresa) use ($defaults) {
            foreach ($defaults as $m) {
                DevolucionMotivo::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'slug' => $m['slug']],
                    [
                        'nombre'                 => $m['nombre'],
                        'afecta_restock_default' => $m['afecta'],
                        'es_sistema'             => true,
                        'activo'                 => true,
                        'orden'                  => $m['orden'],
                    ]
                );
            }
        });
    }
}
