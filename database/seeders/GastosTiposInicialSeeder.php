<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use Illuminate\Database\Seeder;

class GastosTiposInicialSeeder extends Seeder
{
    public function run(): void
    {
        $estructura = [
            [
                'nombre'    => 'Administrativo',
                'categoria' => 'administrativo',
                'conceptos' => ['Alquiler', 'Luz', 'Agua', 'Internet', 'Teléfono', 'Contabilidad', 'Publicidad'],
            ],
            [
                'nombre'    => 'Operativo',
                'categoria' => 'operativo',
                'conceptos' => ['Útiles de limpieza', 'Útiles de oficina', 'Compra de insumos', 'Taxi/Delivery', 'Mantenimiento', 'Empaques/Bolsas'],
            ],
            [
                'nombre'    => 'Otro',
                'categoria' => 'otro',
                'conceptos' => ['Otros gastos'],
            ],
        ];

        Empresa::all()->each(function (Empresa $empresa) use ($estructura) {
            foreach ($estructura as $item) {
                $tipo = GastoTipo::create([
                    'empresa_id' => $empresa->id,
                    'nombre'     => $item['nombre'],
                    'categoria'  => $item['categoria'],
                    'activo'     => true,
                ]);

                foreach ($item['conceptos'] as $concepto) {
                    GastoConcepto::create([
                        'empresa_id'    => $empresa->id,
                        'gasto_tipo_id' => $tipo->id,
                        'nombre'        => $concepto,
                        'activo'        => true,
                    ]);
                }
            }
        });
    }
}
