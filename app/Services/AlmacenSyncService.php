<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Entrada;
use App\Models\Local;
use App\Models\Transferencia;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AlmacenSyncService
{
    /**
     * Sincroniza los almacenes de una empresa al crear un Local nuevo.
     *
     * Modo simple:
     *   - Solo se permite 1 local. Si ya existe otro, lanza excepción.
     *   - Crea 1 almacén tipo='local' ligado a este local.
     *
     * Modo central_y_local:
     *   - Asegura que exista el almacén central de la empresa.
     *   - Crea 1 almacén tipo='local' ligado a este local.
     */
    public function sincronizarTrasCrearLocal(Local $local): void
    {
        $empresa = $local->empresa()->firstOrFail();

        DB::transaction(function () use ($empresa, $local) {
            if ($empresa->usaModoSimple()) {
                $otrosLocales = Local::where('empresa_id', $empresa->id)
                    ->where('id', '!=', $local->id)
                    ->exists();

                if ($otrosLocales) {
                    throw new RuntimeException(
                        'La empresa está en modo "simple" (un solo local). '
                        . 'Cambia el modo de almacén a "central + local" antes de crear más locales.'
                    );
                }

                Almacen::create([
                    'empresa_id' => $empresa->id,
                    'local_id'   => $local->id,
                    'nombre'     => 'Almacén ' . $local->nombre,
                    'tipo'       => 'local',
                    'activo'     => true,
                ]);

                return;
            }

            // central_y_local
            Almacen::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'tipo'       => 'central',
                    'local_id'   => null,
                ],
                [
                    'nombre' => 'Almacén Central',
                    'activo' => true,
                ]
            );

            Almacen::create([
                'empresa_id' => $empresa->id,
                'local_id'   => $local->id,
                'nombre'     => 'Almacén ' . $local->nombre,
                'tipo'       => 'local',
                'activo'     => true,
            ]);
        });
    }

    /**
     * Aplica los cambios necesarios cuando una empresa cambia de modo_almacen.
     * Se llama DESPUÉS de actualizar el campo modo_almacen.
     *
     * Reglas:
     *   - simple → central_y_local: crea el almacén central. Los almacenes locales existentes se mantienen.
     *   - central_y_local → simple:
     *       * Solo permitido si la empresa tiene exactamente 1 local
     *       * Solo permitido si NO hay movimientos en el almacén central
     *       * Elimina el almacén central
     */
    public function aplicarCambioModo(Empresa $empresa, string $modoAnterior): void
    {
        $modoActual = $empresa->modo_almacen;

        if ($modoActual === $modoAnterior) {
            return;
        }

        if ($this->tieneMovimientos($empresa)) {
            throw new RuntimeException(
                'No se puede cambiar el modo de almacén porque la empresa ya tiene movimientos '
                . '(entradas, transferencias o ventas). Para cambiar el modo, debe partir de cero.'
            );
        }

        DB::transaction(function () use ($empresa, $modoActual) {
            if ($modoActual === 'central_y_local') {
                // Asegurar que exista el central
                Almacen::firstOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'tipo'       => 'central',
                        'local_id'   => null,
                    ],
                    [
                        'nombre' => 'Almacén Central',
                        'activo' => true,
                    ]
                );
                return;
            }

            // central_y_local → simple
            $cantidadLocales = Local::where('empresa_id', $empresa->id)->count();
            if ($cantidadLocales !== 1) {
                throw new RuntimeException(
                    'Para usar modo "simple" la empresa debe tener exactamente 1 local. '
                    . "Actualmente tiene {$cantidadLocales}."
                );
            }

            // Eliminar el almacén central (no debe tener movimientos por la verificación previa)
            Almacen::where('empresa_id', $empresa->id)
                ->where('tipo', 'central')
                ->delete();
        });
    }

    /**
     * Retorna true si la empresa tiene movimientos en algún almacén.
     * Usado para bloquear cambios estructurales que dejarían stock huérfano.
     */
    public function tieneMovimientos(Empresa $empresa): bool
    {
        $almacenIds = Almacen::where('empresa_id', $empresa->id)->pluck('id');

        if ($almacenIds->isEmpty()) {
            return false;
        }

        if (Entrada::whereIn('almacen_id', $almacenIds)->exists()) {
            return true;
        }

        $hayTransferencias = Transferencia::whereIn('almacen_origen_id', $almacenIds)
            ->orWhereIn('almacen_destino_id', $almacenIds)
            ->exists();
        if ($hayTransferencias) {
            return true;
        }

        if (Venta::where('empresa_id', $empresa->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * true si el modo de la empresa puede cambiarse en este momento.
     */
    public function puedeCambiarModo(Empresa $empresa): bool
    {
        return !$this->tieneMovimientos($empresa);
    }
}
