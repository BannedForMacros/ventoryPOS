<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;

/**
 * Flota y viajes de reparto: función opcional por empresa (empresas.usa_flota).
 * Apagada, el menú no la muestra y sus pantallas responden 403.
 */
class Flota
{
    public static function activa(?Empresa $empresa): bool
    {
        return (bool) ($empresa?->usa_flota ?? false);
    }

    /**
     * Reglas de la ficha de flota que puede traer un gasto (placa, y si es
     * combustible o mantenimiento, sus datos). La forma; lo que depende del
     * concepto lo valida FlotaService::sincronizarGasto.
     */
    public static function reglasGasto(): array
    {
        return [
            'vehiculo_id'                => ['nullable', 'integer', EnEmpresa::existe('vehiculos')],
            'combustible'                => ['nullable', 'array'],
            'combustible.km'             => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'combustible.galones'        => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'combustible.tanque_lleno'   => ['nullable', 'boolean'],
            'combustible.conductor_id'   => ['nullable', 'integer', EnEmpresa::existe('conductores')],
            'combustible.grifo'          => ['nullable', 'string', 'max:80'],
            'mantenimiento'              => ['nullable', 'array'],
            'mantenimiento.tipo'         => ['nullable', 'in:preventivo,correctivo'],
            'mantenimiento.km'           => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'mantenimiento.taller'       => ['nullable', 'string', 'max:100'],
            'mantenimiento.detalle'      => ['nullable', 'string', 'max:500'],
            'mantenimiento.dias_parado'  => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public static function exigir(?User $user): void
    {
        abort_unless(self::activa($user?->empresa), 403,
            'La flota y los viajes no están activados para tu empresa (Configuración → Entregas).');
    }
}
