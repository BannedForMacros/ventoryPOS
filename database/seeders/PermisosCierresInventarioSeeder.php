<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisosCierresInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $modulo = Modulo::where('slug', 'inventario.cierres')->firstOrFail();

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulo) {
            Permiso::updateOrCreate(
                ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true]
            );
        });
    }
}
