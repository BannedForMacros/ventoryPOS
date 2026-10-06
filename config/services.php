<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Visor de ventas: lee con Claude la foto del cuaderno de ventas.
    // Sin ANTHROPIC_API_KEY la función responde un aviso claro y no llama a nada.
    'anthropic' => [
        'api_key'      => env('ANTHROPIC_API_KEY'),
        'modelo_visor' => env('ANTHROPIC_MODELO_VISOR', 'claude-opus-5-5'),
        // 'demo' = página de ejemplo sin gastar créditos (nunca en producción).
        'lector'       => env('VISOR_VENTAS_LECTOR'),
        'esfuerzo'     => env('ANTHROPIC_ESFUERZO_VISOR', 'medium'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'decolecta' => [
        'token'    => env('DECOLECTA_TOKEN'),
        'base_url' => env('DECOLECTA_BASE_URL', 'https://api.decolecta.pe'),
    ],

];
