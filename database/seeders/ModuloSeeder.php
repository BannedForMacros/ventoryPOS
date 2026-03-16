<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModuloSeeder extends Seeder
{
    public function run(): void
    {
        $dashboard = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Dashboard',
            'slug'     => 'dashboard',
            'icono'    => 'LayoutDashboard',
            'ruta'     => '/dashboard',
            'orden'    => 1,
            'activo'   => true,
        ]);

        $config = Modulo::create([
            'padre_id' => null,
            'nombre'   => 'Configuración',
            'slug'     => 'configuracion',
            'icono'    => 'Settings',
            'ruta'     => null,
            'orden'    => 2,
            'activo'   => true,
        ]);

        $hijos = [
            ['slug' => 'config.empresas', 'nombre' => 'Empresas',          'icono' => 'Building2', 'ruta' => '/configuracion/empresas', 'orden' => 1],
            ['slug' => 'config.locales',  'nombre' => 'Locales',           'icono' => 'MapPin',    'ruta' => '/configuracion/locales',  'orden' => 2],
            ['slug' => 'config.roles',    'nombre' => 'Roles',             'icono' => 'Shield',    'ruta' => '/configuracion/roles',    'orden' => 3],
            ['slug' => 'config.usuarios', 'nombre' => 'Usuarios',          'icono' => 'Users',     'ruta' => '/configuracion/usuarios', 'orden' => 4],
            ['slug' => 'config.modulos',  'nombre' => 'Módulos',           'icono' => 'Layers',    'ruta' => '/configuracion/modulos',  'orden' => 5],
            ['slug' => 'config.permisos', 'nombre' => 'Permisos por Rol',  'icono' => 'Lock',      'ruta' => '/configuracion/permisos', 'orden' => 6],
        ];

        foreach ($hijos as $hijo) {
            Modulo::create([
                'padre_id' => $config->id,
                'nombre'   => $hijo['nombre'],
                'slug'     => $hijo['slug'],
                'icono'    => $hijo['icono'],
                'ruta'     => $hijo['ruta'],
                'orden'    => $hijo['orden'],
                'activo'   => true,
            ]);
        }
    }
}
