<?php

use App\Http\Controllers\Api\AsignacionPlanController;
use App\Http\Controllers\Api\AutenticacionController;
use App\Http\Controllers\Api\BusquedaController;
use App\Http\Controllers\Api\CarteraController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\EdificioController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\ExcelController;
use App\Http\Controllers\Api\FotoEquipoController;
use App\Http\Controllers\Api\MantenimientoController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\OrdenTrabajoController;
use App\Http\Controllers\Api\PanelController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PlanillaController;
use App\Http\Controllers\Api\RegistroAuditoriaController;
use App\Http\Controllers\Api\ResumenController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\TecnicoController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

// RF-001: rutas públicas de autenticación.
Route::post('/iniciar-sesion', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:iniciar-sesion');
Route::post('/recuperar-contrasena', [AutenticacionController::class, 'recuperarContrasena'])->middleware('throttle:recuperar-contrasena');
Route::post('/restablecer-contrasena', [AutenticacionController::class, 'restablecerContrasena'])->middleware('throttle:restablecer-contrasena');
Route::get('/verificar-correo/{usuario}/{hash}', [AutenticacionController::class, 'verificarCorreo'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verificacion.verificar');

// Reporte de fallas por el QR de la cabina, sin iniciar sesión.
Route::get('/publico/ascensores/{codigo}', [ServicioController::class, 'ascensorPublico'])->middleware('throttle:30,1');
Route::post('/publico/ascensores/{codigo}/reportes', [ServicioController::class, 'reportarPorQr'])->middleware('throttle:reporte-qr');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/cerrar-sesion', [AutenticacionController::class, 'cerrarSesion']);
    Route::get('/perfil', [AutenticacionController::class, 'perfil']);
    Route::put('/perfil/contrasena', [AutenticacionController::class, 'cambiarContrasena']);
    Route::get('/resumen', [ResumenController::class, 'index']);
    Route::get('/panel', PanelController::class);
    Route::get('/buscar', BusquedaController::class)->middleware('throttle:60,1');

    // RF-002: roles y permisos.
    Route::get('/roles', [RolController::class, 'index']);
    Route::get('/roles/{rol}', [RolController::class, 'show']);
    Route::post('/roles', [RolController::class, 'store']);
    Route::patch('/roles/{rol}', [RolController::class, 'update']);
    Route::put('/roles/{rol}/permisos', [RolController::class, 'sincronizarPermisos']);
    Route::get('/permisos', [PermisoController::class, 'index']);

    // RF-003: usuarios (no se eliminan, se desactivan).
    Route::patch('/usuarios/{usuario}/alternar-estado', [UsuarioController::class, 'alternarEstado']);
    Route::apiResource('usuarios', UsuarioController::class)->except(['destroy']);

    // RF-004: auditoría.
    Route::get('/registros-auditoria', [RegistroAuditoriaController::class, 'index']);
    Route::get('/registros-auditoria/modulos', [RegistroAuditoriaController::class, 'modulos']);

    // RF-005 y RF-006: clientes, contactos y edificios.
    Route::get('/clientes/{cliente}/estructura', [ClienteController::class, 'estructura']);
    Route::post('/clientes/{cliente}/contactos', [ClienteController::class, 'agregarContacto']);
    Route::apiResource('clientes', ClienteController::class);
    Route::apiResource('edificios', EdificioController::class);

    // RF-007, RF-008 y RF-009: equipos, ascensores e historial.
    Route::get('/equipos/{equipo}/historial', [EquipoController::class, 'historial']);
    Route::post('/equipos/{equipo}/historial', [EquipoController::class, 'agregarHistorial']);
    Route::get('/equipos/{equipo}/fotos', [FotoEquipoController::class, 'index']);
    Route::post('/equipos/{equipo}/fotos', [FotoEquipoController::class, 'store']);
    Route::get('/equipos/{equipo}/fotos/{foto}/archivo', [FotoEquipoController::class, 'archivo']);
    Route::delete('/equipos/{equipo}/fotos/{foto}', [FotoEquipoController::class, 'destroy']);
    Route::apiResource('equipos', EquipoController::class);

    // Notificaciones del usuario autenticado.
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::patch('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'marcarLeida']);

    // Configuración del sistema y periodicidades (RF-011).
    Route::get('/configuracion', [ConfiguracionController::class, 'index']);
    Route::put('/configuracion', [ConfiguracionController::class, 'update']);
    Route::get('/periodicidades', [ConfiguracionController::class, 'periodicidades']);
    Route::get('/festivos', [ConfiguracionController::class, 'festivos']);

    // RF-010, RF-012 y RF-015: planes, precios por periodicidad e historial de precios.
    Route::put('/planes/{plan}/precios', [PlanController::class, 'actualizarPrecios']);
    Route::get('/planes/{plan}/historial-precios', [PlanController::class, 'historialPrecios']);
    Route::apiResource('planes', PlanController::class)->parameters(['planes' => 'plan']);

    // RF-013 a RF-016: asignación de planes a ascensores.
    Route::patch('/asignaciones/{asignacion}/plan', [AsignacionPlanController::class, 'cambiarPlan']);
    Route::patch('/asignaciones/{asignacion}/precio', [AsignacionPlanController::class, 'cambiarPrecio']);
    Route::patch('/asignaciones/{asignacion}/vigencia', [AsignacionPlanController::class, 'cambiarVigencia']);
    Route::patch('/asignaciones/{asignacion}/finalizar', [AsignacionPlanController::class, 'finalizar']);
    Route::apiResource('asignaciones', AsignacionPlanController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['asignaciones' => 'asignacion']);

    // RF-017 y RF-018: calendario de mantenimientos preventivos.
    Route::get('/mantenimientos', [MantenimientoController::class, 'index']);
    Route::get('/mantenimientos/{mantenimiento}', [MantenimientoController::class, 'show']);
    Route::patch('/mantenimientos/{mantenimiento}/reprogramar', [MantenimientoController::class, 'reprogramar']);
    Route::patch('/mantenimientos/{mantenimiento}/cancelar', [MantenimientoController::class, 'cancelar']);
    Route::patch('/mantenimientos/{mantenimiento}/ejecutar', [MantenimientoController::class, 'ejecutar']);

    // RF-019 a RF-021: contratos y renovaciones.
    Route::post('/contratos/{contrato}/seguimientos', [ContratoController::class, 'registrarSeguimiento']);
    Route::post('/contratos/{contrato}/renovar', [ContratoController::class, 'renovar']);
    Route::patch('/contratos/{contrato}/cancelar', [ContratoController::class, 'cancelar']);
    Route::apiResource('contratos', ContratoController::class)->except(['destroy']);

    // RF-022: técnicos, especialidades y disponibilidad.
    Route::get('/especialidades', [TecnicoController::class, 'especialidades']);
    Route::get('/tecnicos/disponibilidad', [TecnicoController::class, 'disponibilidad']);
    Route::get('/tecnicos', [TecnicoController::class, 'index']);
    Route::post('/tecnicos', [TecnicoController::class, 'store']);
    Route::get('/tecnicos/{usuario}', [TecnicoController::class, 'show']);
    Route::put('/tecnicos/{usuario}/perfil', [TecnicoController::class, 'guardarPerfil']);
    Route::patch('/tecnicos/{usuario}/estado', [TecnicoController::class, 'cambiarEstado']);

    // RF-023 a RF-029: agenda y órdenes de trabajo.
    Route::get('/ordenes/agenda', [OrdenTrabajoController::class, 'agenda']);
    Route::put('/ordenes/{orden}/tecnicos', [OrdenTrabajoController::class, 'asignar']);
    Route::patch('/ordenes/{orden}/reprogramar', [OrdenTrabajoController::class, 'reprogramar']);
    Route::patch('/ordenes/{orden}/prioridad', [OrdenTrabajoController::class, 'prioridad']);
    Route::patch('/ordenes/{orden}/estado', [OrdenTrabajoController::class, 'estado']);
    Route::patch('/ordenes/{orden}/cancelar', [OrdenTrabajoController::class, 'cancelar']);
    Route::apiResource('ordenes', OrdenTrabajoController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['ordenes' => 'orden']);

    // Fase 4 (RF-030 a RF-039): planilla, fotos, firmas, correctivo derivado e informe PDF.
    // Carga y descarga en Excel (plantilla, exportación por módulo e importación en dos pasos).
    Route::get('/excel/plantilla', [ExcelController::class, 'plantilla']);
    Route::get('/excel/exportar/{modulo}', [ExcelController::class, 'exportar']);
    Route::post('/excel/importaciones', [ExcelController::class, 'previsualizar']);
    Route::post('/excel/importaciones/{id}/confirmar', [ExcelController::class, 'confirmar']);
    Route::get('/excel/importaciones/{id}/errores', [ExcelController::class, 'errores']);

    // Cartera mínima (la facturación completa llega en la fase 5).
    // Servicio comercial: certificación anual, emergencias 24/7, cotizaciones, indicadores y documentos.
    Route::get('/certificaciones', [ServicioController::class, 'tableroCertificaciones']);
    Route::get('/equipos/{equipo}/certificaciones', [ServicioController::class, 'certificacionesEquipo']);
    Route::post('/equipos/{equipo}/certificaciones', [ServicioController::class, 'registrarCertificacion']);
    Route::post('/certificaciones/{certificacion}/archivo', [ServicioController::class, 'adjuntarCertificado']);
    Route::get('/certificaciones/{certificacion}/archivo', [ServicioController::class, 'archivoCertificado']);
    Route::get('/equipos/{equipo}/qr', [ServicioController::class, 'codigoQr']);
    Route::get('/equipos/{equipo}/documentos', [ServicioController::class, 'documentosEquipo']);
    Route::get('/emergencias', [ServicioController::class, 'emergencias']);
    Route::post('/emergencias', [ServicioController::class, 'reportarEmergencia']);
    Route::get('/emergencias/{emergencia}', [ServicioController::class, 'verEmergencia']);
    Route::post('/emergencias/{emergencia}/estado', [ServicioController::class, 'cambiarEstadoEmergencia']);
    Route::get('/cotizaciones', [ServicioController::class, 'cotizaciones']);
    Route::post('/cotizaciones', [ServicioController::class, 'crearCotizacion']);
    Route::get('/cotizaciones/{cotizacion}', [ServicioController::class, 'verCotizacion']);
    Route::post('/cotizaciones/{cotizacion}/respuesta', [ServicioController::class, 'responderCotizacion']);
    Route::get('/indicadores/servicio', [ServicioController::class, 'indicadores']);

    Route::get('/cartera/resumen', [CarteraController::class, 'resumen']);
    Route::get('/facturas', [CarteraController::class, 'facturas']);
    Route::post('/facturas', [CarteraController::class, 'crearFactura']);
    Route::get('/facturas/{factura}', [CarteraController::class, 'verFactura']);
    Route::post('/pagos', [CarteraController::class, 'registrarPago']);

    Route::get('/checklist/items', [PlanillaController::class, 'catalogo']);
    Route::get('/ordenes/{orden}/planilla', [PlanillaController::class, 'show']);
    Route::put('/ordenes/{orden}/planilla', [PlanillaController::class, 'guardar']);
    Route::post('/ordenes/{orden}/planilla/fotos', [PlanillaController::class, 'agregarFoto']);
    Route::get('/ordenes/{orden}/planilla/fotos/{foto}/archivo', [PlanillaController::class, 'foto']);
    Route::delete('/ordenes/{orden}/planilla/fotos/{foto}', [PlanillaController::class, 'eliminarFoto']);
    Route::post('/ordenes/{orden}/planilla/resultados/{resultado}/correctivo', [PlanillaController::class, 'crearCorrectivo']);
    Route::post('/ordenes/{orden}/firmas', [PlanillaController::class, 'firmar']);
    Route::get('/ordenes/{orden}/firmas/{firma}/archivo', [PlanillaController::class, 'firma']);
    Route::get('/ordenes/{orden}/informes', [PlanillaController::class, 'informes']);
    Route::post('/ordenes/{orden}/informes', [PlanillaController::class, 'nuevaVersion']);
    Route::get('/ordenes/{orden}/informes/{informe}/pdf', [PlanillaController::class, 'pdf']);
});
