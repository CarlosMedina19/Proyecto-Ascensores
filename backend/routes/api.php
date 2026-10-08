<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Fase 1: Fundación y Datos Maestros
|--------------------------------------------------------------------------
*/

// Rutas públicas de autenticación (RF-001)
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación y perfil
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Roles y permisos (RF-002)
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);

    // Gestión de usuarios (RF-003)
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class);

    // Auditoría (RF-004)
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Clientes y contactos (RF-005)
    Route::post('/clients/{client}/contacts', [ClientController::class, 'addContact']);
    Route::apiResource('clients', ClientController::class);

    // Edificios (RF-006)
    Route::apiResource('buildings', BuildingController::class);

    // Equipos, ascensores, puertas eléctricas e historial (RF-007, RF-008, RF-009, RF-010)
    Route::get('/equipment/{equipment}/history', [EquipmentController::class, 'history']);
    Route::post('/equipment/{equipment}/history', [EquipmentController::class, 'addHistory']);
    Route::apiResource('equipment', EquipmentController::class);
});