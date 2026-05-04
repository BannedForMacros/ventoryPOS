<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Local;
use App\Services\AlmacenSyncService;
use Illuminate\Database\Seeder;

class AlmacenSeeder extends Seeder
{
    public function run(AlmacenSyncService $sync): void
    {
        $empresa = Empresa::where('ruc', '20612345678')->firstOrFail();

        // Limpia almacenes previos para que la reejecución del seeder
        // genere los almacenes coherentes con el modo actual de la empresa.
        Almacen::where('empresa_id', $empresa->id)->delete();

        $locales = Local::where('empresa_id', $empresa->id)->orderBy('id')->get();

        foreach ($locales as $local) {
            $sync->sincronizarTrasCrearLocal($local);
        }
    }
}
