<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class ClientesInicialSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::all()->each(function (Empresa $empresa) {
            Cliente::firstOrCreate(
                [
                    'empresa_id'       => $empresa->id,
                    'numero_documento' => null,
                    'nombres'          => 'Cliente',
                ],
                [
                    'tipo_documento' => 'otro',
                    'apellidos'      => 'General',
                    'activo'         => true,
                ]
            );
        });
    }
}
