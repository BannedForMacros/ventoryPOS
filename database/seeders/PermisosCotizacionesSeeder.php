<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisosCotizacionesSeeder extends Seeder
{
    /**
     * Permisos del módulo Cotizaciones para los roles administradores de
     * TODAS las empresas (patrón PermisosReportesSeeder). Los demás roles se
     * configuran desde Configuración → Permisos por Rol. Idempotente.
     */
    public function run(): void
    {
        $modulos = Modulo::whereIn('slug', ['ventas.cotizaciones'])->get();

        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulos) {
            foreach ($modulos as $modulo) {
                Permiso::updateOrCreate(
                    ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                    ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true]
                );
            }
        });
    }
}
