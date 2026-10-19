<?php

use App\Services\TareasProgramadasService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Tareas programadas de SIGMA (Laravel Scheduler).
 * En producción se ejecutan con `php artisan schedule:work` (servicio "scheduler" de Docker)
 * y los envíos con `php artisan queue:work` (servicio "queue").
 */

Artisan::command('sigma:procesar-vigencias', function (TareasProgramadasService $tareas) {
    $resultado = $tareas->procesarVigencias();
    $this->info("Planes finalizados: {$resultado['asignaciones_finalizadas']}. Contratos vencidos: {$resultado['contratos_vencidos']}.");
})->purpose('Finaliza los planes y contratos cuya vigencia terminó (RF-014, RF-020)');

Artisan::command('sigma:enviar-avisos', function (TareasProgramadasService $tareas) {
    $avisos = $tareas->enviarAvisosMantenimiento();
    $alertas = $tareas->alertarVencimientoContratos();
    $this->info("Avisos de mantenimiento encolados: {$avisos}. Alertas de vencimiento de contratos: {$alertas}.");
})->purpose('Envía los avisos de mantenimiento y las alertas de renovación (RF-020, RF-021)');

Schedule::command('sigma:procesar-vigencias')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('sigma:enviar-avisos')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->weekly();
