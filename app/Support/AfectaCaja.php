<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use App\Models\Turno;

/**
 * Registro central de "Afecta caja" (opt-in por empresa y por módulo).
 *
 * Antes, la lógica de "este movimiento afecta la caja de un turno" estaba
 * duplicada en ~8 controladores y ~7 páginas. Aquí vive UNA sola vez:
 *
 *   · MODULOS         → qué módulos exponen el selector "Afecta caja" y su
 *                       default (encendido/apagado) cuando la empresa no lo ha
 *                       tocado. Agregar un módulo = una línea aquí.
 *   · resuelto()      → mapa completo (defaults ∪ config de la empresa) que se
 *                       serializa al frontend para pintar el selector y la UI
 *                       de configuración.
 *   · activo()        → ¿la empresa tiene el módulo encendido?
 *   · resolverTurno() → regla única para los controladores: qué turno_id se
 *                       imputa a un movimiento (o null = no afecta caja).
 *
 * La config se guarda en empresas.afecta_caja_config (JSONB), como mapa
 *   { "deuda": {"activo": true}, "gastos": {"activo": false}, ... }
 */
class AfectaCaja
{
    /**
     * Módulos gobernados por el sistema unificado.
     *   label       → texto para la pantalla de Configuración → Empresa.
     *   default     → estado si la empresa nunca lo configuró.
     *   disponible  → si el módulo ya está migrado al componente compartido.
     *                 Los que aún usan su selector inline salen como
     *                 "Próximamente" (toggle deshabilitado) para no prometer
     *                 un control que todavía no gobierna ese módulo.
     *
     * Ventas/POS NO está aquí a propósito: su turno_id es obligatorio (el POS
     * vive dentro de un turno), no es opt-in.
     */
    public const MODULOS = [
        'deuda'        => ['label' => 'Préstamos y pagos de deuda', 'default' => true,  'disponible' => true],
        'entradas'     => ['label' => 'Entradas (compras)',          'default' => false, 'disponible' => false],
        'cxp'          => ['label' => 'Cuentas por pagar (abonos)',   'default' => true,  'disponible' => false],
        'cxc'          => ['label' => 'Cuentas por cobrar (abonos)',  'default' => true,  'disponible' => false],
        'anticipos'    => ['label' => 'Anticipos de cliente',         'default' => true,  'disponible' => false],
        'gastos'       => ['label' => 'Gastos',                       'default' => true,  'disponible' => false],
        'devoluciones' => ['label' => 'Devoluciones',                 'default' => true,  'disponible' => false],
    ];

    /**
     * Mapa completo de config para una empresa: cada módulo con su `activo`
     * (config guardada o, en su defecto, el default del registro) y metadatos
     * (label, disponible) para la UI. Se usa como accessor en Empresa y viaja
     * al frontend.
     *
     * @return array<string, array{activo: bool, label: string, disponible: bool}>
     */
    public static function resuelto(Empresa $empresa): array
    {
        $guardado = $empresa->afecta_caja_config ?? [];
        $mapa = [];

        foreach (self::MODULOS as $key => $meta) {
            $activoGuardado = $guardado[$key]['activo'] ?? null;
            $mapa[$key] = [
                'activo'     => is_bool($activoGuardado) ? $activoGuardado : $meta['default'],
                'label'      => $meta['label'],
                'disponible' => $meta['disponible'],
            ];
        }

        return $mapa;
    }

    /** ¿La empresa tiene "Afecta caja" encendido para este módulo? */
    public static function activo(Empresa $empresa, string $modulo): bool
    {
        return self::resuelto($empresa)[$modulo]['activo'] ?? false;
    }

    /**
     * Regla ÚNICA de imputación de turno para un movimiento de un módulo.
     * Reemplaza los bloques inline repetidos en cada controlador.
     *
     *   · Módulo apagado para la empresa   → null (nunca afecta caja).
     *   · Cajero con turno activo           → su turno (forzado), ignora lo pedido.
     *   · Admin (o cajero sin turno)        → el turno solicitado (ya validado)
     *                                          o null si eligió "Sin turno".
     *
     * @param  int|null  $turnoSolicitado  turno_id que mandó el form (admin).
     */
    public static function resolverTurno(User $user, string $modulo, ?int $turnoSolicitado): ?int
    {
        $empresa = $user->empresa;
        if (! $empresa || ! self::activo($empresa, $modulo)) {
            return null;
        }

        $esAdmin = (bool) ($user->rol?->es_admin ?? false);

        if (! $esAdmin) {
            $activo = Turno::turnoActivoDelUsuario($user->id);
            return $activo?->id;
        }

        return $turnoSolicitado;
    }
}
