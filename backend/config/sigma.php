<?php

/*
 * Parámetros de negocio de SIGMA. Se configuran por entorno para no
 * dejar valores fijos en el código.
 */
return [

    'sesion' => [
        // RF-001: la sesión se cierra tras 10 minutos de inactividad.
        'minutos_inactividad' => (int) env('SIGMA_MINUTOS_INACTIVIDAD', 10),
        // RF-001: 5 intentos fallidos consecutivos bloquean la cuenta 15 minutos.
        'intentos_maximos' => (int) env('SIGMA_INTENTOS_MAXIMOS', 5),
        'minutos_bloqueo' => (int) env('SIGMA_MINUTOS_BLOQUEO', 15),
        // Verificación en dos pasos: días que un dispositivo ya verificado no vuelve a pedir el código.
        'dias_dispositivo_confianza' => (int) env('SIGMA_DIAS_DISPOSITIVO_CONFIANZA', 180),
    ],

    // Respaldos fuera del servidor: los volcados diarios (carpeta montada desde sigma_respaldos) se suben
    // al Object Storage. La vigilancia avisa si el último respaldo remoto tiene más de 30 horas.
    'respaldos' => [
        'carpeta' => env('SIGMA_RUTA_RESPALDOS', storage_path('respaldos')),
        'disco_remoto' => env('SIGMA_DISCO_RESPALDOS', 'objetos'),
        'dias_remotos' => (int) env('SIGMA_DIAS_RESPALDO_REMOTO', 30),
        'vigilar' => (bool) env('SIGMA_VIGILAR_RESPALDOS', false),
    ],

    // «Roles y permisos»: el administrador puede ver la contraseña de la cuenta principal de cada rol
    // (confirmando la suya). En la instalación de un cliente real conviene desactivarlo.
    'mostrar_credenciales' => (bool) env('SIGMA_MOSTRAR_CREDENCIALES', true),

    // Vigilancia: porcentaje mínimo de disco libre antes de avisar a los administradores.
    'vigilancia' => [
        'disco_minimo' => (float) env('SIGMA_DISCO_MINIMO_LIBRE', 10),
    ],

    // Límite general de solicitudes a la API por usuario (o por IP sin sesión) y por minuto.
    'limite_api_por_minuto' => (int) env('SIGMA_LIMITE_API_POR_MINUTO', 300),

    // URL pública del frontend web, usada en los enlaces de los correos.
    'url_frontend' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
     * Permisos que solo puede tener el rol administrador
     * (RF-002, RF-003 y RF-004: solo el administrador gestiona roles,
     * permisos y usuarios, y consulta la auditoría).
     */
    'permisos_exclusivos_administrador' => [
        'roles.crear',
        'roles.editar',
        'roles.permisos.editar',
        'permisos.ver',
        'usuarios.crear',
        'usuarios.editar',
        'registros_auditoria.ver',
        'configuracion.editar',
        // RF-022: registrar un técnico crea su cuenta de usuario.
        'tecnicos.crear',
    ],
];
