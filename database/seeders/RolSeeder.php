<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20612345678')->firstOrFail();

        Rol::create([
            'empresa_id' => $empresa->id,
            'nombre'     => 'Administrador',
            'es_admin'   => true,
            'activo'     => true,
        ]);
    }
}
