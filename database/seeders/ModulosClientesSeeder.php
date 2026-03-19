<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosClientesSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo directo: Clientes (sin hijos)
        Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Clientes',
            'slug'     => 'clientes',
            'icono'    => 'Users',
            'ruta'     => '/clientes',
            'orden'    => 30,
            'activo'   => true,
        ]);

        // HIJO de Configuración: Métodos de pago
        $configuracion = Modulo::where('slug', 'configuracion')->firstOrFail();

        Modulo::create([
            'padre_id' => $configuracion->id,
            'nombre'   => 'Métodos de pago',
            'slug'     => 'configuracion.metodos-pago',
            'icono'    => 'Wallet',
            'ruta'     => '/configuracion/metodos-pago',
            'orden'    => 6,
            'activo'   => true,
        ]);
    }
}
