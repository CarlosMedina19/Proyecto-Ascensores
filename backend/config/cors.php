<?php

/*
 * El frontend web (React) y Flutter Web consumen la API desde otro origen.
 * La API usa tokens Bearer, no cookies, por eso no se envían credenciales.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGENES', env('FRONTEND_URL', 'http://localhost:5173'))))),

    // En desarrollo se acepta cualquier puerto de localhost (Vite, Flutter Web).
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? ['#^http://(localhost|127\.0\.0\.1)(:\d+)?$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
