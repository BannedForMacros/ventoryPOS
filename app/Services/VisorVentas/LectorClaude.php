<?php

namespace App\Services\VisorVentas;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

/**
 * Lee con Claude la foto de una página del cuaderno de ventas.
 *
 * Solo transcribe: el cruce con el catálogo lo hace la base de datos
 * (VisorVentasService), así no se manda el catálogo en cada foto y cada
 * lectura cuesta lo mismo que la imagen más su respuesta.
 */
class LectorClaude implements LectorCuaderno
{
    private const MAX_TOKENS = 8000;

    private const INSTRUCCIONES = <<<'TXT'
    Lees fotos del cuaderno donde una botica de Perú anota sus ventas a mano.

    Cómo está escrito:
    - La página puede tener varias columnas. Léelas de izquierda a derecha y cada una de arriba abajo.
    - Una fecha escrita arriba de una columna (por ejemplo "05/10/26") vale para las ventas que siguen, hasta la próxima fecha.
    - Cada VENTA es un grupo de renglones unidos por una llave "}" o una flecha ">" con UN solo total al lado. Un renglón suelto con ">" y un monto es una venta de un solo producto.
    - Cada renglón es: cantidad + nombre del producto, muchas veces abreviado o con faltas ("parac" = paracetamol, "ibupro" = ibuprofeno).
    - Las cantidades son por unidad (tabletas, sobres, frascos), no por caja.
    - Si un monto está tachado y corregido, vale el último.

    Reglas:
    - No inventes. "texto" es lo que está escrito, tal cual lo lees.
    - "interpretacion" es tu mejor apuesta del nombre completo del producto (nombre genérico o comercial común en boticas de Perú), o null si no tienes idea.
    - "seguro" es false si dudas del producto o de la cantidad.
    - "total" es el monto de la venta en soles, o null si no se lee. "cantidad" es null si no se lee.
    - "fecha" es la fecha tal como está escrita (DD/MM/AA), o null si la columna no tiene.
    TXT;

    public function leer(string $imagenBase64, string $mediaType): array
    {
        $clave = config('services.anthropic.api_key');
        if (!$clave) {
            throw new LecturaFallida('El visor de ventas aún no está configurado (falta la clave de la API). Avísale al administrador del sistema.');
        }

        $modelo = config('services.anthropic.modelo_visor');
        // "max" o "xhigh" disparan el costo de cada foto: solo se aceptan estos.
        $esfuerzo = in_array(config('services.anthropic.esfuerzo'), ['low', 'medium', 'high'], true)
            ? config('services.anthropic.esfuerzo') : 'medium';

        try {
            $client  = new Client(apiKey: $clave);
            $mensaje = $client->beta->messages->create(
                model: $modelo,
                // Una página llena del cuaderno son ~3.000 tokens; el tope acota
                // lo que puede costar una foto rara.
                maxTokens: self::MAX_TOKENS,
                system: self::INSTRUCCIONES,
                messages: [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => $imagenBase64]],
                        ['type' => 'text', 'text' => 'Transcribe todas las ventas de esta página.'],
                    ],
                ]],
                outputConfig: [
                    'effort' => $esfuerzo,
                    'format' => ['type' => 'json_schema', 'schema' => self::esquema()],
                ],
                // Si el modelo declina por política, el servidor reintenta con su
                // modelo de respaldo dentro de la misma llamada.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                // El SDK no corta solo: el tiempo lo pone el transporte. Un solo
                // reintento (cada uno se cobra) y antes de que el servidor web corte.
                requestOptions: [
                    'maxRetries'  => 1,
                    'transporter' => new \GuzzleHttp\Client(['timeout' => 110, 'connect_timeout' => 10]),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Visor de ventas: falló la llamada a Claude', ['error' => $e->getMessage()]);
            throw new LecturaFallida('No se pudo leer la foto en este momento. Intenta de nuevo en unos minutos.', 0, $e);
        }

        // De aquí en adelante la API ya respondió: la lectura se cobró aunque falle.
        $cobrada = fn (string $msj) => new LecturaFallida(
            $msj, 0, null, true, $mensaje->model, $mensaje->usage->inputTokens ?? null, $mensaje->usage->outputTokens ?? null,
        );

        if ($mensaje->stopReason === 'refusal') {
            throw $cobrada('La foto no se pudo procesar. Prueba con otra foto de la página.');
        }
        if ($mensaje->stopReason === 'max_tokens') {
            throw $cobrada('La página tiene demasiadas ventas para una sola foto. Toma la foto por partes (una columna a la vez).');
        }

        $json = null;
        foreach ($mensaje->content as $bloque) {
            if ($bloque->type === 'text') {
                $json = json_decode($bloque->text, true);
                break;
            }
        }
        if (!is_array($json) || !isset($json['ventas']) || !is_array($json['ventas'])) {
            throw $cobrada('No se entendió la foto. Toma otra con buena luz, de frente y con la página completa.');
        }

        return [
            'ventas'         => $json['ventas'],
            'modelo'         => $mensaje->model,
            'tokens_entrada' => $mensaje->usage->inputTokens ?? null,
            'tokens_salida'  => $mensaje->usage->outputTokens ?? null,
        ];
    }

    /** Lo que devuelve Claude: ventas → renglones. */
    private static function esquema(): array
    {
        $numeroONulo = ['anyOf' => [['type' => 'number'], ['type' => 'null']]];
        $textoONulo  = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['ventas'],
            'properties'           => [
                'ventas' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['fecha', 'total', 'items'],
                        'properties'           => [
                            'fecha' => $textoONulo,
                            'total' => $numeroONulo,
                            'items' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'                 => 'object',
                                    'additionalProperties' => false,
                                    'required'             => ['cantidad', 'texto', 'interpretacion', 'seguro'],
                                    'properties'           => [
                                        'cantidad'       => $numeroONulo,
                                        'texto'          => ['type' => 'string'],
                                        'interpretacion' => $textoONulo,
                                        'seguro'         => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
