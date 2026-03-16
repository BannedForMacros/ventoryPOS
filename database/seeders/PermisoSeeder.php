<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $rol     = Rol::where('nombre', 'Administrador')->firstOrFail();
        $modulos = Modulo::all();

        foreach ($modulos as $modulo) {
            Permiso::create([
                'rol_id'    => $rol->id,
                'modulo_id' => $modulo->id,
                'ver'       => true,
                'crear'     => true,
                'editar'    => true,
                'eliminar'  => true,
            ]);
        }
    }
}
