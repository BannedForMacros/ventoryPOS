<?php

namespace Database\Seeders;

use App\Models\DescuentoConcepto;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class DescuentoConceptosInicialSeeder extends Seeder
{
    public function run(): void
    {
        $conceptos = [
            ['nombre' => 'Descuento por volumen',       'requiere_aprobacion' => false],
            ['nombre' => 'Descuento a cliente frecuente','requiere_aprobacion' => false],
            ['nombre' => 'Descuento por campaña',        'requiere_aprobacion' => false],
            ['nombre' => 'Descuento especial gerencia',  'requiere_aprobacion' => true],
            ['nombre' => 'Descuento por cortesía',       'requiere_aprobacion' => true],
        ];

        foreach (Empresa::all() as $empresa) {
            foreach ($conceptos as $concepto) {
                DescuentoConcepto::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'nombre' => $concepto['nombre']],
                    ['requiere_aprobacion' => $concepto['requiere_aprobacion'], 'activo' => true]
                );
            }
        }
    }
}
