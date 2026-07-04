<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * F0 — Módulo Finanzas: cuentas por cobrar/pagar, anticipos de clientes,
 * adelantos a proveedores, deudas/préstamos y balance diario.
 *
 * Idempotente: usa firstOrCreate/updateOrCreate para poder re-ejecutarse.
 */
class ModulosFinanzasSeeder extends Seeder
{
    public function run(): void
    {
        $finanzas = Modulo::firstOrCreate(
            ['slug' => 'finanzas'],
            [
                'padre_id' => null,
                'nombre'   => 'Finanzas',
                'icono'    => 'Wallet',
                'ruta'     => null,
                'orden'    => 25,
                'activo'   => true,
            ],
        );

        $hijos = [
            ['slug' => 'finanzas.balance',           'nombre' => 'Balance diario',       'icono' => 'Scale',          'ruta' => '/finanzas/balance',            'orden' => 1],
            ['slug' => 'finanzas.cuentas-por-cobrar','nombre' => 'Cuentas por cobrar',   'icono' => 'HandCoins',      'ruta' => '/finanzas/cuentas-por-cobrar', 'orden' => 2],
            ['slug' => 'finanzas.cuentas-por-pagar', 'nombre' => 'Cuentas por pagar',    'icono' => 'Banknote',       'ruta' => '/finanzas/cuentas-por-pagar',  'orden' => 3],
            ['slug' => 'finanzas.anticipos',         'nombre' => 'Anticipos de clientes','icono' => 'PiggyBank',      'ruta' => '/finanzas/anticipos',          'orden' => 4],
            ['slug' => 'finanzas.adelantos',         'nombre' => 'Adelantos a proveedores','icono' => 'ArrowUpCircle','ruta' => '/finanzas/adelantos',          'orden' => 5],
            ['slug' => 'finanzas.deudas',            'nombre' => 'Deudas y préstamos',   'icono' => 'Landmark',       'ruta' => '/finanzas/deudas',             'orden' => 6],
        ];

        $modulos = collect([$finanzas]);

        foreach ($hijos as $hijo) {
            $modulos->push(Modulo::firstOrCreate(
                ['slug' => $hijo['slug']],
                [
                    'padre_id' => $finanzas->id,
                    'nombre'   => $hijo['nombre'],
                    'icono'    => $hijo['icono'],
                    'ruta'     => $hijo['ruta'],
                    'orden'    => $hijo['orden'],
                    'activo'   => true,
                ],
            ));
        }

        // Permisos completos para todos los roles admin.
        Rol::where('es_admin', true)->each(function (Rol $rol) use ($modulos) {
            foreach ($modulos as $modulo) {
                Permiso::updateOrCreate(
                    ['rol_id' => $rol->id, 'modulo_id' => $modulo->id],
                    ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true],
                );
            }
        });
    }
}
