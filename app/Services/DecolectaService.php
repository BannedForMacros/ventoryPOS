<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DecolectaService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.decolecta.base_url');
        $this->token   = config('services.decolecta.token');
    }

    /**
     * Consulta DNI en RENIEC vía Decolecta.
     * GET /v1/reniec/dni?numero={dni}
     * Retorna: ['nombres' => '', 'apellidos' => '']
     *
     * @throws \Exception si el DNI no existe o la API falla
     */
    public function consultarDni(string $dni): array
    {
        $response = Http::withToken($this->token)
            ->timeout(8)
            ->get("{$this->baseUrl}/v1/reniec/dni", ['numero' => $dni]);

        if (!$response->successful()) {
            throw new \Exception('No se encontró el DNI o el servicio RENIEC no está disponible.');
        }

        $data = $response->json();

        // first_last_name = apellido paterno, second_last_name = apellido materno
        $apellidos = trim(($data['first_last_name'] ?? '') . ' ' . ($data['second_last_name'] ?? ''));

        return [
            'nombres'   => $data['first_name'] ?? '',
            'apellidos' => $apellidos,
        ];
    }

    /**
     * Consulta RUC en SUNAT vía Decolecta.
     * GET /v1/sunat/ruc?numero={ruc}
     * Retorna: ['razon_social' => '', 'direccion' => '']
     *
     * @throws \Exception si el RUC no existe o la API falla
     */
    public function consultarRuc(string $ruc): array
    {
        $response = Http::withToken($this->token)
            ->timeout(8)
            ->get("{$this->baseUrl}/v1/sunat/ruc", ['numero' => $ruc]);

        if (!$response->successful()) {
            throw new \Exception('No se encontró el RUC o el servicio SUNAT no está disponible.');
        }

        $data = $response->json();

        return [
            'razon_social' => $data['razon_social'] ?? '',
            'direccion'    => $data['direccion']    ?? '',
        ];
    }

    /**
     * Tipo de cambio contable SBS vía Decolecta.
     * GET /v1/tipo-cambio/sbs/accounting?currency={moneda}[&date=Y-m-d]
     * Respuesta: {"price":"3.546000","base_currency":"USD","quote_currency":"PEN","date":"2025-07-25"}
     *
     * @return array{tasa: float, fecha: string, base: string, quote: string, raw: array}
     * @throws \Exception si la SBS no publicó tipo de cambio para esa fecha o la API falla
     */
    public function tipoCambioSbs(string $moneda = 'USD', ?string $fecha = null): array
    {
        $query = ['currency' => strtoupper($moneda)];
        if ($fecha) {
            $query['date'] = substr($fecha, 0, 10);
        }

        $response = Http::withToken($this->token)
            ->timeout(8)
            ->get("{$this->baseUrl}/v1/tipo-cambio/sbs/accounting", $query);

        if (!$response->successful()) {
            throw new \Exception("No hay tipo de cambio SBS para {$moneda}" . ($fecha ? " al {$fecha}" : '') . '.');
        }

        $data = $response->json();

        return [
            'tasa'  => (float) ($data['price'] ?? 0),
            'fecha' => $data['date'] ?? substr((string) $fecha, 0, 10),
            'base'  => $data['base_currency']  ?? strtoupper($moneda),
            'quote' => $data['quote_currency'] ?? 'PEN',
            'raw'   => is_array($data) ? $data : [],
        ];
    }
}
