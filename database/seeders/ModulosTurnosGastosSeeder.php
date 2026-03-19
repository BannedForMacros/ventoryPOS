<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosTurnosGastosSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo directo: Turnos (sin hijos)
        Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Turnos',
            'slug'     => 'turnos',
            'icono'    => 'Clock',
            'ruta'     => '/turnos',
            'orden'    => 40,
            'activo'   => true,
        ]);

        // Módulo directo: Gastos (sin hijos)
        Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Gastos',
            'slug'     => 'gastos',
            'icono'    => 'Receipt',
            'ruta'     => '/gastos',
            'orden'    => 50,
            'activo'   => true,
        ]);

        // HIJOS de Configuración
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Cajas',
            'slug'     => 'configuracion.cajas',
            'icono'    => 'Monitor',
            'ruta'     => '/configuracion/cajas',
            'orden'    => 7,
            'activo'   => true,
        ]);

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Tipos de gasto',
            'slug'     => 'configuracion.gastos-tipos',
            'icono'    => 'Tags',
            'ruta'     => '/configuracion/gastos/tipos',
            'orden'    => 8,
            'activo'   => true,
        ]);
    }
}
