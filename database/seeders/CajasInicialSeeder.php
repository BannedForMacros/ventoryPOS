<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Local;
use Illuminate\Database\Seeder;

class CajasInicialSeeder extends Seeder
{
    public function run(): void
    {
        Local::all()->each(function (Local $local) {
            Caja::create([
                'empresa_id'                => $local->empresa_id,
                'local_id'                  => $local->id,
                'nombre'                    => 'Caja principal',
                'caja_chica_activa'         => false,
                'caja_chica_monto_sugerido' => 0,
                'caja_chica_en_arqueo'      => false,
                'activo'                    => true,
            ]);
        });
    }
}
