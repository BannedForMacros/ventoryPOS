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
        'deuda'        => ['label' => 'Préstamos y pagos de deuda', 'default' => false, 'disponible' => true, 'modo' => 'forzado'],
        'entradas'     => ['label' => 'Entradas (compras)',          'default' => true,  'disponible' => true, 'modo' => 'libre'],
        'cxp'          => ['label' => 'Cuentas por pagar (abonos)',   'default' => true,  'disponible' => true, 'modo' => 'libre'],
        'cxc'          => ['label' => 'Cuentas por cobrar (abonos)',  'default' => true,  'disponible' => true, 'modo' => 'libre'],
        'anticipos'    => ['label' => 'Anticipos de cliente',         'default' => true,  'disponible' => true, 'modo' => 'libre'],
        'gastos'       => ['label' => 'Gastos',                       'default' => true,  'disponible' => true, 'modo' => 'forzado'],
        'devoluciones' => ['label' => 'Devoluciones',                 'default' => true,  'disponible' => true, 'modo' => 'forzado'],
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
     * Primero SIEMPRE gatea por config: módulo apagado → null (nunca afecta
     * caja). Luego, según el modo del módulo:
     *
     *   · 'forzado' (gastos, devoluciones): el cajero se imputa a su turno
     *     activo (ignora lo pedido); el admin usa el turno solicitado. Es el
     *     patrón donde el cajero NO puede elegir "Sin turno".
     *   · 'libre' (entradas, cxp, cxc, anticipos): se respeta lo elegido para
     *     TODOS (incluido "Sin turno" = null). El cajero decide (opt-in).
     *
     * @param  int|null  $turnoSolicitado  turno_id que mandó el form.
     */
    public static function resolverTurno(User $user, string $modulo, ?int $turnoSolicitado, string $modo = 'forzado'): ?int
    {
        $empresa = $user->empresa;
        if (! $empresa || ! self::activo($empresa, $modulo)) {
            return null;
        }

        if ($modo === 'libre') {
            return $turnoSolicitado;
        }

        // 'forzado'
        $esAdmin = (bool) ($user->rol?->es_admin ?? false);
        if (! $esAdmin) {
            return Turno::turnoActivoDelUsuario($user->id)?->id;
        }

        return $turnoSolicitado;
    }
}
