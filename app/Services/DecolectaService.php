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
}
