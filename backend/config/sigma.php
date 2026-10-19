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
    ],

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
