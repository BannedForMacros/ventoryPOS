<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\SalidaTipo;
use Illuminate\Database\Seeder;

class SalidaTiposInicialSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['nombre' => 'Merma',                'slug' => 'merma',    'orden' => 10],
            ['nombre' => 'Ajuste de inventario', 'slug' => 'ajuste',   'orden' => 20],
            ['nombre' => 'Baja / Destrucción',   'slug' => 'baja',     'orden' => 30],
            ['nombre' => 'Consumo interno',      'slug' => 'consumo',  'orden' => 40],
            ['nombre' => 'Otro',                 'slug' => 'otro',     'orden' => 99],
        ];

        Empresa::query()->each(function (Empresa $empresa) use ($defaults) {
            foreach ($defaults as $tipo) {
                SalidaTipo::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'slug' => $tipo['slug']],
                    [
                        'nombre'     => $tipo['nombre'],
                        'es_sistema' => true,
                        'activo'     => true,
                        'orden'      => $tipo['orden'],
                    ]
                );
            }
        });
    }
}
