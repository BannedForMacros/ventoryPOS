<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de auditoria. Centraliza la creacion de registros de Auditoria.
 *
 * Diseno: explicito, no automatico. Solo se loggea cuando algun caller invoca
 * AuditoriaService::log(). Esto evita ruido de auditoria sobre cada UPDATE/INSERT
 * trivial y deja claro en el codigo que esa accion es "sensible".
 *
 * Uso tipico:
 *
 *   AuditoriaService::log('venta.anulada', $venta, [
 *       'numero' => $venta->numero,
 *       'total'  => (float) $venta->total,
 *       'motivo' => $request->input('motivo'),
 *   ]);
 *
 * Las fallas al loggear NO interrumpen la operacion principal: la auditoria es
 * deseable pero nunca debe romper una venta. Los errores se silencian a log.
 */
class AuditoriaService
{
    /**
     * Registra una accion auditada.
     *
     * @param string      $accion    Slug semantico (ej. 'venta.anulada').
     * @param Model|null  $modelo    Modelo afectado (opcional).
     * @param array       $contexto  Datos adicionales (motivo, snapshot, etc.).
     * @param User|null   $user      Usuario que realiza la accion. Si null, usa auth()->user().
     */
    public static function log(
        string $accion,
        ?Model $modelo = null,
        array $contexto = [],
        ?User $user = null,
    ): ?Auditoria {
        try {
            $user ??= auth()->user();
            if (!$user) return null; // sin usuario no podemos auditar (ej. comandos cli sin auth)

            $request = request();

            return Auditoria::create([
                'empresa_id'  => $user->empresa_id,
                'user_id'     => $user->id,
                'user_name'   => $user->name,
                'accion'      => $accion,
                'modelo_tipo' => $modelo ? get_class($modelo) : null,
                'modelo_id'   => $modelo?->getKey(),
                'contexto'    => empty($contexto) ? null : $contexto,
                'ip'          => $request?->ip(),
                'user_agent'  => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoria nunca debe bloquear la operacion principal.
            \Log::warning('AuditoriaService::log fallo', [
                'accion'  => $accion,
                'error'   => $e->getMessage(),
                'modelo'  => $modelo ? get_class($modelo).':'.$modelo->getKey() : null,
            ]);
            return null;
        }
    }
}
