<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class MetodosPagoInicialSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['nombre' => 'Efectivo',      'tipo' => 'efectivo'],
            ['nombre' => 'Tarjeta',       'tipo' => 'tarjeta_debito'],
            ['nombre' => 'Yape',          'tipo' => 'yape'],
            ['nombre' => 'Plin',          'tipo' => 'plin'],
            ['nombre' => 'Transferencia', 'tipo' => 'transferencia'],
        ];

        Empresa::all()->each(function (Empresa $empresa) use ($metodos) {
            foreach ($metodos as $metodo) {
                MetodoPago::firstOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'nombre'     => $metodo['nombre'],
                    ],
                    [
                        'tipo'   => $metodo['tipo'],
                        'activo' => true,
                    ]
                );
            }
        });
    }
}
