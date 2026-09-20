<?php

namespace App\Services\Guias;

use App\Models\Cliente;
use App\Models\Venta;

/**
 * Del formulario de ventoryPOS al payload que espera FacturaMac.
 *
 * ─── AQUÍ NO SE DECIDE NADA FISCAL ─────────────────────────────────────────────
 *
 * Ni motivos, ni códigos de SUNAT, ni qué campos exige cada caso. Eso vive en el
 * contrato compartido y lo aplica el emisor. Este servicio solo traduce lo que hay
 * en ventoryPOS —un cliente, unos productos, un almacén— a la forma que el contrato
 * espera, y se queda ahí.
 *
 * Meter reglas fiscales en este lado es exactamente lo que se quiso evitar desde el
 * principio: dos sistemas con su propia idea de lo que SUNAT pide acaban
 * discrepando, y el aviso llega como rechazo con la guía ya numerada.
 *
 * ─── LO QUE SOBRA NO SE MANDA ──────────────────────────────────────────────────
 *
 * El contrato rechaza lo que sobra, no solo lo que falta: un transportista en una
 * guía de vehículo propio, o un destinatario en un traslado entre almacenes. Esa
 * poda se hace aquí, que es donde se sabe qué eligió la persona.
 */
final class GuiaAContrato
{
    /**
     * @param  array<string, mixed> $form Lo que llega del formulario.
     * @return array<string, mixed>
     */
    public function mapear(array $form, int $empresaId): array
    {
        $traslado = [
            'motivo'       => $form['motivo'],
            'modalidad'    => $form['modalidad'],
            'fecha_inicio' => $form['fecha_inicio'],
            'peso_total'   => (float) $form['peso_total'],
            'unidad_peso'  => $form['unidad_peso'] ?? 'KGM',
            'partida'      => $this->punto($form['partida'] ?? []),
            'llegada'      => $this->punto($form['llegada'] ?? []),
        ];

        if (filled($form['numero_bultos'] ?? null)) {
            $traslado['numero_bultos'] = (int) $form['numero_bultos'];
        }

        if (filled($form['descripcion_motivo'] ?? null)) {
            $traslado['descripcion_motivo'] = $form['descripcion_motivo'];
        }

        foreach (['retorno_vehiculo_vacio', 'retorno_envases_vacios', 'transbordo_programado'] as $indicador) {
            if (! empty($form[$indicador])) {
                $traslado[$indicador] = true;
            }
        }

        $traslado += $this->transporte($form);

        $payload = [
            'idempotency_key'    => $form['idempotency_key'],
            'referencia_externa' => $form['referencia_externa'],
            'traslado'           => $traslado,
            'items'              => $this->items($form['items'] ?? []),
        ];

        if (filled($form['observaciones'] ?? null)) {
            $payload['observaciones'] = $form['observaciones'];
        }

        // El destinatario solo viaja si el motivo lo admite. Quién lo decide es el
        // contrato, y el formulario ya recibió esa regla en `/guias/catalogos`: aquí
        // se respeta lo que la pantalla dejó puesto.
        if (filled($form['cliente_id'] ?? null)) {
            $payload['destinatario'] = $this->cliente(
                Cliente::where('empresa_id', $empresaId)->findOrFail($form['cliente_id']),
            );
        }

        if (filled($form['comprador_id'] ?? null)) {
            $payload['comprador'] = $this->cliente(
                Cliente::where('empresa_id', $empresaId)->findOrFail($form['comprador_id']),
            );
        }

        if (filled($form['venta_id'] ?? null)) {
            $payload['comprobantes'] = $this->comprobantesDe((int) $form['venta_id'], $empresaId);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function punto(array $datos): array
    {
        $punto = [
            'ubigeo'    => (string) ($datos['ubigeo'] ?? ''),
            'direccion' => (string) ($datos['direccion'] ?? ''),
        ];

        // El código de local solo cabe en el extremo que es de la empresa. Cuál de
        // los dos lo es lo dice el contrato, y la pantalla solo enseña el campo
        // donde toca: si llega vacío, no se manda.
        if (filled($datos['codigo_establecimiento'] ?? null)) {
            $punto['codigo_establecimiento'] = $datos['codigo_establecimiento'];
        }

        return $punto;
    }

    /**
     * El bloque de transporte: uno u otro, nunca los dos.
     *
     * @return array<string, mixed>
     */
    private function transporte(array $form): array
    {
        if (($form['modalidad'] ?? null) === 'PUBLICO') {
            return ['transportista' => array_filter([
                'ruc'          => $form['transportista']['ruc'] ?? null,
                'razon_social' => $form['transportista']['razon_social'] ?? null,
                'registro_mtc' => $form['transportista']['registro_mtc'] ?? null,
            ], static fn ($v) => filled($v))];
        }

        // Moto o auto particular: SUNAT exime de declarar placa y conductor.
        if (! empty($form['vehiculo_menor'])) {
            return ['vehiculo_menor' => true];
        }

        $conductor = $form['conductor'] ?? [];

        return [
            'vehiculo' => array_filter([
                'placa'               => $form['vehiculo']['placa'] ?? null,
                'tarjeta_circulacion' => $form['vehiculo']['tarjeta_circulacion'] ?? null,
            ], static fn ($v) => filled($v)),
            'conductores' => [[
                'tipo_documento'   => $conductor['tipo_documento'] ?? 'DNI',
                'numero_documento' => $conductor['numero_documento'] ?? '',
                'nombres'          => $conductor['nombres'] ?? '',
                'apellidos'        => $conductor['apellidos'] ?? '',
                'licencia'         => $conductor['licencia'] ?? '',
                'principal'        => true,
            ]],
        ];
    }

    /**
     * Las líneas. SIN PRECIOS, y no por olvido: una guía es un documento de
     * movimiento, no de valor, y un importe aquí acabaría impreso en el papel que
     * viaja en la cabina del camión.
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $items): array
    {
        return array_values(array_map(static function (array $i): array {
            $linea = [
                'descripcion' => (string) $i['descripcion'],
                'cantidad'    => (float) $i['cantidad'],
                'unidad'      => $i['unidad'] ?? 'NIU',
            ];

            if (filled($i['codigo'] ?? null)) {
                $linea['codigo'] = $i['codigo'];
            }

            // El peso por línea solo sirve para proponer el total; la cifra que se
            // declara es la que la persona confirma, porque el bruto incluye
            // embalaje y parihuelas que el sistema no conoce.
            if (filled($i['peso'] ?? null)) {
                $linea['peso'] = (float) $i['peso'];
            }

            return $linea;
        }, $items));
    }

    /** @return array<string, mixed> */
    private function cliente(Cliente $cliente): array
    {
        return array_filter([
            'documento' => [
                'tipo'   => $cliente->tipo_documento,
                'numero' => $cliente->numero_documento,
            ],
            'nombre'    => $cliente->razon_social ?: $cliente->nombre_completo,
            'direccion' => $cliente->direccion,
        ], static fn ($v) => filled($v));
    }

    /**
     * Los comprobantes de la venta que respalda el traslado.
     *
     * Solo los que SUNAT ya conoce: amparar una guía con una factura que todavía no
     * salió es decir que existe algo que no existe.
     *
     * @return list<array<string, mixed>>
     */
    private function comprobantesDe(int $ventaId, int $empresaId): array
    {
        $venta = Venta::where('empresa_id', $empresaId)
            ->with('comprobanteElectronico')
            ->find($ventaId);

        $ce = $venta?->comprobanteElectronico;

        if ($ce === null || ! $ce->esEmitido() || ! filled($ce->serie) || ! filled($ce->correlativo)) {
            return [];
        }

        return [[
            'tipo'   => $ce->tipo === '01' ? 'factura' : 'boleta',
            'serie'  => $ce->serie,
            'numero' => (int) $ce->correlativo,
        ]];
    }
}
