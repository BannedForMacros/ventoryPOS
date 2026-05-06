<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisosDevolucionesSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = Modulo::whereIn('slug', [
            'devoluciones',
            'configuracion.devolucion-motivos',
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
