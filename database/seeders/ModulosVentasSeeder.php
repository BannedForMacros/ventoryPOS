<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosVentasSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo directo: POS (sin hijos)
        Modulo::create([
            'padre_id' => null,
            'nombre'   => 'POS',
            'slug'     => 'pos',
            'icono'    => 'ShoppingCart',
            'ruta'     => '/pos',
            'orden'    => 35,
            'activo'   => true,
        ]);

        // Módulo directo: Ventas (historial)
        Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Ventas',
            'slug'     => 'ventas',
            'icono'    => 'ReceiptText',
            'ruta'     => '/ventas',
            'orden'    => 36,
            'activo'   => true,
        ]);

        // Reportes (padre)
        $reportes = Modulo::firstOrCreate(
            ['slug' => 'reportes'],
            [
                'padre_id' => null,
                'nombre'   => 'Reportes',
                'icono'    => 'BarChart2',
                'ruta'     => null,
                'orden'    => 70,
                'activo'   => true,
            ]
        );

        Modulo::create([
            'padre_id' => $reportes->id,
            'nombre'   => 'Descuentos',
            'slug'     => 'reportes.descuentos',
            'icono'    => 'Tag',
            'ruta'     => '/reportes/descuentos',
            'orden'    => 1,
            'activo'   => true,
        ]);

        // Hijo de Configuración: Conceptos de descuento
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Conceptos de descuento',
            'slug'     => 'configuracion.descuento-conceptos',
            'icono'    => 'Percent',
            'ruta'     => '/configuracion/descuento-conceptos',
            'orden'    => 9,
            'activo'   => true,
        ]);
    }
}
