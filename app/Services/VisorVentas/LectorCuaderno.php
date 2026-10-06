<?php

namespace App\Services\VisorVentas;

/**
 * Quien lee la foto del cuaderno. Hoy Claude; mañana puede ser un modelo local
 * (Ollama en una PC con GPU) sin tocar el resto del visor.
 */
interface LectorCuaderno
{
    /**
     * @return array{ventas: list<array{fecha: ?string, total: ?float, items: list<array{cantidad: ?float, texto: string, interpretacion: ?string, seguro: bool}>}>, modelo: ?string, tokens_entrada: ?int, tokens_salida: ?int}
     *
     * @throws LecturaFallida con un mensaje para mostrar a la cajera
     */
    public function leer(string $imagenBase64, string $mediaType): array;
}
