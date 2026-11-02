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

    public static function exigir(?User $user): void
    {
        abort_unless(self::activa($user?->empresa), 403,
            'La flota y los viajes no están activados para tu empresa (Configuración → Entregas).');
    }
}
