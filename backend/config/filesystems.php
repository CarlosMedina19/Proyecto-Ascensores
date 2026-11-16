<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    | Disco de los archivos de SIGMA (fotos, firmas, certificados e informes): «objetos» en producción
    | (Object Storage) o «local» en desarrollo y pruebas.
    */
    'archivos' => env('SIGMA_DISCO_ARCHIVOS', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Object Storage de SIGMA (S3 compatible, p. ej. Contabo). Solo el backend conoce las llaves;
        // el prefijo separa producción y desarrollo dentro del bucket de SIGMA.
        'objetos' => [
            'driver' => 's3',
            'key' => env('OBJECT_STORAGE_ACCESS_KEY'),
            'secret' => env('OBJECT_STORAGE_SECRET_KEY'),
            'region' => env('OBJECT_STORAGE_REGION', 'default'),
            'bucket' => env('OBJECT_STORAGE_BUCKET'),
            'endpoint' => env('OBJECT_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT', true),
            'root' => env('OBJECT_STORAGE_PREFIX', ''),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
            'http' => ['connect_timeout' => 10, 'timeout' => 60],
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
