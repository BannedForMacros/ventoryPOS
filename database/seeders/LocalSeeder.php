<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Local;
use Illuminate\Database\Seeder;

class LocalSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20612345678')->firstOrFail();

        Local::create([
            'empresa_id'  => $empresa->id,
            'nombre'      => 'Local Principal',
            'es_principal'=> true,
            'activo'      => true,
        ]);
    }
}
