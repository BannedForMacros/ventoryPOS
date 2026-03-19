<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class ModulosCuentasSeeder extends Seeder
{
    public function run(): void
    {
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Cuentas',
            'slug'     => 'configuracion.cuentas',
            'icono'    => 'Landmark',
            'ruta'     => '/configuracion/cuentas',
            'orden'    => 7,
            'activo'   => true,
        ]);

        $modulo = Modulo::where('slug', 'configuracion.cuentas')->first();

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulo) {
            Permiso::updateOrCreate(
                ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true]
            );
        });
    }
}
