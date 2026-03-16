<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::create([
            'razon_social'    => 'MacSoft E.I.R.L.',
            'ruc'             => '20612345678',
            'nombre_comercial'=> 'MacSoft',
            'activo'          => true,
        ]);
    }
}
