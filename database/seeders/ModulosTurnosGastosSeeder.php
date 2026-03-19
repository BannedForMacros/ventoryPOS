<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosTurnosGastosSeeder extends Seeder
{
    public function run(): void
    {
        // PADRE: Turnos
        $turnos = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Turnos',
            'slug'     => 'turnos',
            'icono'    => 'Clock',
            'ruta'     => null,
            'orden'    => 40,
            'activo'   => true,
        ]);

        Modulo::create([
            'padre_id' => $turnos->id,
            'nombre'   => 'Turnos',
            'slug'     => 'turnos.index',
            'icono'    => 'Clock',
            'ruta'     => '/turnos',
            'orden'    => 1,
            'activo'   => true,
        ]);

        // PADRE: Gastos
        $gastos = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Gastos',
            'slug'     => 'gastos',
            'icono'    => 'Receipt',
            'ruta'     => null,
            'orden'    => 50,
            'activo'   => true,
        ]);

        Modulo::create([
            'padre_id' => $gastos->id,
            'nombre'   => 'Gastos',
            'slug'     => 'gastos.index',
            'icono'    => 'Receipt',
            'ruta'     => '/gastos',
            'orden'    => 1,
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
