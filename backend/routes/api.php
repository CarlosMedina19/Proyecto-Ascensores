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

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);

    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::apiResource('users', UserController::class)->except(['destroy']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::post('/clients/{client}/contacts', [ClientController::class, 'addContact']);
    Route::apiResource('clients', ClientController::class);

    Route::apiResource('buildings', BuildingController::class);

    Route::get('/equipment/{equipment}/history', [EquipmentController::class, 'history']);
    Route::post('/equipment/{equipment}/history', [EquipmentController::class, 'addHistory']);
    Route::apiResource('equipment', EquipmentController::class);
});
