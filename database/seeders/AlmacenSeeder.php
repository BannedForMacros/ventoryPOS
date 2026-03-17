<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Local;
use Illuminate\Database\Seeder;

class AlmacenSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20612345678')->firstOrFail();
        $local   = Local::where('empresa_id', $empresa->id)->where('es_principal', true)->firstOrFail();

        // Almacén central (no pertenece a ningún local)
        Almacen::create([
            'empresa_id' => $empresa->id,
            'local_id'   => null,
            'nombre'     => 'Almacén Central',
            'tipo'       => 'central',
            'activo'     => true,
        ]);

        // Almacén del local principal
        Almacen::create([
            'empresa_id' => $empresa->id,
            'local_id'   => $local->id,
            'nombre'     => 'Almacén ' . $local->nombre,
            'tipo'       => 'local',
            'activo'     => true,
        ]);
    }
}
