<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\MetodoPago;
use App\Models\TipoMetodoPago;
use Illuminate\Database\Seeder;

class MetodosPagoInicialSeeder extends Seeder
{
    public function run(): void
    {
        // El catalogo `tipos_metodo_pago` debe estar poblado por su migracion.
        $tipos = TipoMetodoPago::pluck('id', 'slug');

        $metodos = [
            ['nombre' => 'Efectivo',      'slug' => 'efectivo'],
            ['nombre' => 'Tarjeta',       'slug' => 'tarjeta_debito'],
            ['nombre' => 'Yape',          'slug' => 'yape'],
            ['nombre' => 'Plin',          'slug' => 'plin'],
            ['nombre' => 'Transferencia', 'slug' => 'transferencia'],
        ];

        Empresa::all()->each(function (Empresa $empresa) use ($metodos, $tipos) {
            foreach ($metodos as $metodo) {
                $tipoId = $tipos[$metodo['slug']] ?? null;
                if (!$tipoId) continue;

                MetodoPago::firstOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'nombre'     => $metodo['nombre'],
                    ],
                    [
                        'tipo_id'       => $tipoId,
                        'admite_vuelto' => $metodo['slug'] === 'efectivo',
                        'activo'        => true,
                    ]
                );
            }
        });
    }
}
