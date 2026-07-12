<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosReportesSeeder extends Seeder
{
    /**
     * Agrega los reportes operativos al módulo padre "Reportes".
     *
     * El padre "Reportes" se crea (si no existe) por ModulosVentasSeeder; aquí
     * sólo agregamos los hijos. Es idempotente: usa firstOrCreate por slug.
     */
    public function run(): void
    {
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

        $hijos = [
            ['slug' => 'reportes.ventas',       'nombre' => 'Ventas',        'icono' => 'TrendingUp',  'ruta' => '/reportes/ventas',       'orden' => 2],
            ['slug' => 'reportes.utilidad',    'nombre' => 'Utilidad',      'icono' => 'CircleDollarSign', 'ruta' => '/reportes/utilidad', 'orden' => 2],
            ['slug' => 'reportes.productos',   'nombre' => 'Productos',     'icono' => 'Package',     'ruta' => '/reportes/productos',    'orden' => 3],
            ['slug' => 'reportes.caja',        'nombre' => 'Caja / Turnos', 'icono' => 'Wallet',      'ruta' => '/reportes/caja',         'orden' => 4],
            ['slug' => 'reportes.gastos',      'nombre' => 'Gastos',        'icono' => 'TrendingDown', 'ruta' => '/reportes/gastos',      'orden' => 5],
            ['slug' => 'reportes.devoluciones','nombre' => 'Devoluciones',  'icono' => 'Undo2',       'ruta' => '/reportes/devoluciones', 'orden' => 6],
        ];

        foreach ($hijos as $h) {
            Modulo::firstOrCreate(
                ['slug' => $h['slug']],
                [
                    'padre_id' => $reportes->id,
                    'nombre'   => $h['nombre'],
                    'icono'    => $h['icono'],
                    'ruta'     => $h['ruta'],
                    'orden'    => $h['orden'],
                    'activo'   => true,
                ]
            );
        }
    }
}
