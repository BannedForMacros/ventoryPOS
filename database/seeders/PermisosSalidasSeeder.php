<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisosSalidasSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = Modulo::whereIn('slug', [
            'inventario.salidas',
            'configuracion.salidas-tipos',
        ])->get();

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulos) {
            foreach ($modulos as $modulo) {
                Permiso::updateOrCreate(
                    ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                    ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true]
                );
            }
        });
    }
}
