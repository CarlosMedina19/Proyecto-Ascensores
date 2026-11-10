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

    // Microservicio de analítica en Python (backend/analitica). Solo en la red interna de Docker.
    'analitica' => [
        'url' => env('ANALITICA_URL', 'http://sigma_analitica:8001'),
        'clave' => env('ANALITICA_CLAVE'),
        'tiempo' => env('ANALITICA_TIEMPO', 20),
    ],

    // Pasarela de pagos Wompi (PSE). Proyecto formativo: llaves de pruebas (pub_test_… / test_integrity_…).
    'wompi' => [
        'llave_publica' => env('WOMPI_LLAVE_PUBLICA'),
        'secreto_integridad' => env('WOMPI_SECRETO_INTEGRIDAD'),
        'secreto_eventos' => env('WOMPI_SECRETO_EVENTOS'),
        'api' => env('WOMPI_API', 'https://sandbox.wompi.co/v1'),
        'checkout' => env('WOMPI_CHECKOUT', 'https://checkout.wompi.co/p/'),
        // Página del panel a la que Wompi devuelve al cliente después de pagar.
        'retorno' => env('WOMPI_RETORNO', env('FRONTEND_URL', 'http://localhost:5173').'/pagos/resultado'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
