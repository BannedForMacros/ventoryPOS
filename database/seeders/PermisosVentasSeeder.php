<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisosVentasSeeder extends Seeder
{
    public function run(): void
    {
        $slugs = ['pos', 'ventas', 'reportes.descuentos', 'configuracion.descuento-conceptos'];

        foreach (Rol::all() as $rol) {
            foreach ($slugs as $slug) {
                $modulo = Modulo::where('slug', $slug)->first();
                if (!$modulo) continue;

                $esAdmin    = $rol->es_admin;
                $esVendedor = str_contains(strtolower($rol->nombre), 'vendedor')
                           || str_contains(strtolower($rol->nombre), 'cajero');

                Permiso::firstOrCreate(
                    ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                    [
                        'ver'      => true,
                        'crear'    => $esAdmin || ($esVendedor && in_array($slug, ['pos', 'ventas'])),
                        'editar'   => $esAdmin,
                        'eliminar' => $esAdmin,
                    ]
                );
            }
        }
    }
}
