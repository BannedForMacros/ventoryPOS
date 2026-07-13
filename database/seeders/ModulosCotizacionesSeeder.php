<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosCotizacionesSeeder extends Seeder
{
    /**
     * Módulo "Cotizaciones" del sidebar. Va como módulo directo junto a POS y
     * Ventas (que son hojas de primer nivel, no un padre expandible: colgarlo
     * de "Ventas" convertiría ese link directo en un submenú y rompería la
     * navegación existente). Idempotente: firstOrCreate por slug.
     */
    public function run(): void
    {
        Modulo::firstOrCreate(
            ['slug' => 'ventas.cotizaciones'],
            [
                'padre_id' => null,
                'nombre'   => 'Cotizaciones',
                'icono'    => 'FileText',
                'ruta'     => '/cotizaciones',
                'orden'    => 37,
                'activo'   => true,
            ]
        );
    }
}
