<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Local;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20612345678')->firstOrFail();
        $local   = Local::where('empresa_id', $empresa->id)->firstOrFail();
        $rol     = Rol::where('nombre', 'Administrador')->firstOrFail();

        User::create([
            'empresa_id' => $empresa->id,
            'local_id'   => $local->id,
            'rol_id'     => $rol->id,
            'name'       => 'Admin MacSoft',
            'email'      => 'admin@macsoft.pe',
            'password'   => Hash::make('password'),
            'activo'     => true,
        ]);
    }
}
