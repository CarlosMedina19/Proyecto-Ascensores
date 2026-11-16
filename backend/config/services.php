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
    // Asistentes con IA (n8n + DeepSeek). Sin n8n activo responde el motor local de SIGMA.
    'asistente' => [
        'n8n_activo' => (bool) env('ASISTENTE_N8N_ACTIVO', false),
        'url_panel' => env('ASISTENTE_N8N_URL_PANEL'),
        'url_landing' => env('ASISTENTE_N8N_URL_LANDING'),
        'clave' => env('ASISTENTE_N8N_CLAVE'),
        'tiempo' => (int) env('ASISTENTE_N8N_TIEMPO', 40),
        // Máximo de preguntas del asistente público que van a la IA (n8n) por día, para acotar el costo.
        // Al pasarlo responde el motor local, sin costo. 0 = sin tope.
        'limite_diario_publico' => (int) env('ASISTENTE_LIMITE_DIARIO_PUBLICO', 500),
        'contacto' => [
            'whatsapp' => env('SIGMA_WHATSAPP', '573000000000'),
            'whatsapp_visible' => env('SIGMA_WHATSAPP_VISIBLE', '+57 300 000 0000'),
            'correo' => env('SIGMA_CORREO_CONTACTO', 'sigma3172409@gmail.com'),
        ],
    ],

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
