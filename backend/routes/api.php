<?php

use App\Http\Controllers\Api\AutenticacionController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EdificioController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\RegistroAuditoriaController;
use App\Http\Controllers\Api\ResumenController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/iniciar-sesion', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:iniciar-sesion');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/cerrar-sesion', [AutenticacionController::class, 'cerrarSesion']);
    Route::get('/perfil', [AutenticacionController::class, 'perfil']);
    Route::get('/resumen', [ResumenController::class, 'index']);

    Route::get('/roles', [RolController::class, 'index']);
    Route::get('/roles/{rol}', [RolController::class, 'show']);
    Route::post('/roles', [RolController::class, 'store']);
    Route::patch('/roles/{rol}', [RolController::class, 'update']);
    Route::put('/roles/{rol}/permisos', [RolController::class, 'sincronizarPermisos']);
    Route::get('/permisos', [PermisoController::class, 'index']);

    Route::patch('/usuarios/{usuario}/alternar-estado', [UsuarioController::class, 'alternarEstado']);
    Route::apiResource('usuarios', UsuarioController::class)->except(['destroy']);

    Route::get('/registros-auditoria', [RegistroAuditoriaController::class, 'index']);

    Route::post('/clientes/{cliente}/contactos', [ClienteController::class, 'agregarContacto']);
    Route::apiResource('clientes', ClienteController::class);

    Route::apiResource('edificios', EdificioController::class);

    Route::get('/equipos/{equipo}/historial', [EquipoController::class, 'historial']);
    Route::post('/equipos/{equipo}/historial', [EquipoController::class, 'agregarHistorial']);
    Route::apiResource('equipos', EquipoController::class);
});
