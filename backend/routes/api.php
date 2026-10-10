<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DomainApiController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\TraducirContratoApiEspanol;
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
    Route::post('/attachments/upload', [AttachmentController::class, 'store']);
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->whereNumber('attachment');

    // Módulos operativos, financieros, IoT e IA (sin puertas eléctricas).
    $domainResources = [
        'maintenance-plans' => 'maintenance-plans',
        'plan-assignments' => 'plan-assignments',
        'plan-price-history' => 'plan-price-history',
        'contracts' => 'contracts',
        'contract-equipment' => 'contract-equipment',
        'technicians' => 'technicians',
        'work-orders' => 'work-orders',
        'work-order-technicians' => 'work-order-technicians',
        'maintenance-schedules' => 'maintenance-schedules',
        'work-order-events' => 'work-order-events',
        'checklist-templates' => 'checklist-templates',
        'checklist-sections' => 'checklist-sections',
        'checklist-items' => 'checklist-items',
        'work-order-checklist' => 'work-order-checklist',
        'parts' => 'parts',
        'work-order-parts' => 'work-order-parts',
        'stock-movements' => 'stock-movements',
        'quotations' => 'quotations',
        'quotation-items' => 'quotation-items',
        'invoices' => 'invoices',
        'invoice-items' => 'invoice-items',
        'payments' => 'payments',
        'account-movements' => 'account-movements',
        'attachments' => 'attachments',
        'signatures' => 'signatures',
        'notification-preferences' => 'notification-preferences',
        'iot/devices' => 'iot/devices',
        'iot/sensors' => 'iot/sensors',
        'iot/readings' => 'iot/readings',
        'iot/events' => 'iot/events',
        'iot/alert-rules' => 'iot/alert-rules',
        'iot/alerts' => 'iot/alerts',
        'ai/documents' => 'ai/documents',
        'ai/document-chunks' => 'ai/document-chunks',
        'ai/embeddings' => 'ai/embeddings',
        'ai/conversations' => 'ai/conversations',
        'ai/messages' => 'ai/messages',
        'ai/feedback' => 'ai/feedback',
        'ai/predictions' => 'ai/predictions',
    ];

    foreach ($domainResources as $path => $resource) {
        Route::get($path, [DomainApiController::class, 'index'])->defaults('resource', $resource);
        Route::post($path, [DomainApiController::class, 'store'])->defaults('resource', $resource);
        Route::get($path.'/{record}', [DomainApiController::class, 'show'])
            ->whereNumber('record')
            ->defaults('resource', $resource);
        Route::match(['put', 'patch'], $path.'/{record}', [DomainApiController::class, 'update'])
            ->whereNumber('record')
            ->defaults('resource', $resource);
        Route::delete($path.'/{record}', [DomainApiController::class, 'destroy'])
            ->whereNumber('record')
            ->defaults('resource', $resource);
    }

    Route::get('/analytics/kpis', [AnalyticsController::class, 'kpis']);
    Route::get('/analytics/anomalies', [AnalyticsController::class, 'anomalies']);
    Route::get('/analytics/predictions', [AnalyticsController::class, 'predictions']);
});

Route::middleware(TraducirContratoApiEspanol::class)->group(function (): void {
    Route::post('/iniciar-sesion', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/cerrar-sesion', [AuthController::class, 'logout']);
        Route::get('/perfil', [AuthController::class, 'me']);
        Route::get('/tablero', [DashboardController::class, 'index']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);

        Route::patch('/usuarios/{user}/cambiar-estado', [UserController::class, 'toggleStatus']);
        Route::apiResource('usuarios', UserController::class)
            ->parameters(['usuarios' => 'user']);

        Route::get('/registros-auditoria', [AuditLogController::class, 'index']);

        Route::post('/clientes/{client}/contactos', [ClientController::class, 'addContact']);
        Route::apiResource('clientes', ClientController::class)
            ->parameters(['clientes' => 'client']);

        Route::apiResource('edificios', BuildingController::class)
            ->parameters(['edificios' => 'building']);

        Route::get('/equipos/{equipment}/historial', [EquipmentController::class, 'history']);
        Route::post('/equipos/{equipment}/historial', [EquipmentController::class, 'addHistory']);
        Route::apiResource('equipos', EquipmentController::class)
            ->parameters(['equipos' => 'equipment']);

        Route::post('/adjuntos/cargar', [AttachmentController::class, 'store']);
        Route::get('/adjuntos/{attachment}/descargar', [AttachmentController::class, 'download'])
            ->whereNumber('attachment');

        $recursosEnEspanol = [
            'planes-mantenimiento' => 'maintenance-plans',
            'asignaciones-planes' => 'plan-assignments',
            'historial-precios-planes' => 'plan-price-history',
            'contratos' => 'contracts',
            'equipos-contratos' => 'contract-equipment',
            'tecnicos' => 'technicians',
            'ordenes-trabajo' => 'work-orders',
            'tecnicos-ordenes-trabajo' => 'work-order-technicians',
            'programaciones-mantenimiento' => 'maintenance-schedules',
            'eventos-ordenes-trabajo' => 'work-order-events',
            'plantillas-verificacion' => 'checklist-templates',
            'secciones-verificacion' => 'checklist-sections',
            'elementos-verificacion' => 'checklist-items',
            'verificaciones-ordenes-trabajo' => 'work-order-checklist',
            'repuestos' => 'parts',
            'repuestos-ordenes-trabajo' => 'work-order-parts',
            'movimientos-inventario' => 'stock-movements',
            'cotizaciones' => 'quotations',
            'conceptos-cotizaciones' => 'quotation-items',
            'facturas' => 'invoices',
            'conceptos-facturas' => 'invoice-items',
            'pagos' => 'payments',
            'movimientos-cuenta' => 'account-movements',
            'adjuntos' => 'attachments',
            'firmas' => 'signatures',
            'preferencias-notificacion' => 'notification-preferences',
            'iot/dispositivos' => 'iot/devices',
            'iot/sensores' => 'iot/sensors',
            'iot/lecturas' => 'iot/readings',
            'iot/eventos' => 'iot/events',
            'iot/reglas-alerta' => 'iot/alert-rules',
            'iot/alertas' => 'iot/alerts',
            'ia/documentos' => 'ai/documents',
            'ia/fragmentos-documentos' => 'ai/document-chunks',
            'ia/incrustaciones' => 'ai/embeddings',
            'ia/conversaciones' => 'ai/conversations',
            'ia/mensajes' => 'ai/messages',
            'ia/comentarios' => 'ai/feedback',
            'ia/predicciones' => 'ai/predictions',
        ];

        foreach ($recursosEnEspanol as $ruta => $recurso) {
            Route::get($ruta, [DomainApiController::class, 'index'])->defaults('resource', $recurso);
            Route::post($ruta, [DomainApiController::class, 'store'])->defaults('resource', $recurso);
            Route::get($ruta.'/{record}', [DomainApiController::class, 'show'])
                ->whereNumber('record')
                ->defaults('resource', $recurso);
            Route::match(['put', 'patch'], $ruta.'/{record}', [DomainApiController::class, 'update'])
                ->whereNumber('record')
                ->defaults('resource', $recurso);
            Route::delete($ruta.'/{record}', [DomainApiController::class, 'destroy'])
                ->whereNumber('record')
                ->defaults('resource', $recurso);
        }

        Route::get('/analitica/indicadores', [AnalyticsController::class, 'kpis']);
        Route::get('/analitica/anomalias', [AnalyticsController::class, 'anomalies']);
        Route::get('/analitica/predicciones', [AnalyticsController::class, 'predictions']);
    });
});
