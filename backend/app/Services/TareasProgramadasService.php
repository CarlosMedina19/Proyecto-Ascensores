<?php

namespace App\Services;

use App\Models\AsignacionPlan;
use App\Models\CambioAsignacion;
use App\Models\Configuracion;
use App\Models\Contrato;
use App\Models\MantenimientoProgramado;
use App\Notifications\AlertaVencimientoContratoNotification;
use App\Notifications\AvisoMantenimientoNotification;
use Illuminate\Support\Facades\DB;

/**
 * Procesos diarios que ejecuta el Scheduler de Laravel.
 */
class TareasProgramadasService
{
    public function __construct(
        private readonly ProgramacionService $programacion,
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * RF-014 y RF-020: planes vencidos dejan de programar; contratos vencidos sin renovar
     * pasan a "vencido" y sus planes ya no programan mantenimientos nuevos.
     *
     * @return array{asignaciones_finalizadas: int, contratos_vencidos: int}
     */
    public function procesarVigencias(): array
    {
        $hoy = now()->toDateString();
        $finalizadas = 0;
        $vencidos = 0;

        AsignacionPlan::activas()->whereDate('fecha_fin', '<', $hoy)->each(function (AsignacionPlan $asignacion) use (&$finalizadas) {
            DB::transaction(function () use ($asignacion) {
                CambioAsignacion::create([
                    'asignacion_id' => $asignacion->id,
                    'campo' => 'estado',
                    'valor_anterior' => 'activa',
                    'valor_nuevo' => 'finalizada',
                    'motivo' => 'Fin de la vigencia del plan',
                ]);
                $asignacion->update(['estado' => 'finalizada']);
                $this->programacion->cancelarPendientes($asignacion, 'Fin de la vigencia del plan');
                RegistroAuditoriaService::registrar('vigencia_terminada', $asignacion, ['estado' => 'finalizada'], null, 'planes');
            });
            $finalizadas++;
        });

        Contrato::where('estado', 'vigente')->whereDate('fecha_fin', '<', $hoy)->each(function (Contrato $contrato) use (&$vencidos) {
            DB::transaction(function () use ($contrato) {
                $contrato->update(['estado' => 'vencido']);
                foreach ($contrato->asignaciones()->activas()->get() as $asignacion) {
                    $asignacion->mantenimientos()->pendientes()
                        ->whereDate('fecha_programada', '>', $contrato->fecha_fin->toDateString())
                        ->update(['estado' => 'cancelado', 'motivo' => "Contrato {$contrato->numero} vencido sin renovar", 'updated_at' => now()]);
                    $this->programacion->actualizarProximo($asignacion);
                }
                RegistroAuditoriaService::registrar('vencido', $contrato, ['estado' => 'vencido'], null, 'contratos');
            });
            $vencidos++;
        });

        return ['asignaciones_finalizadas' => $finalizadas, 'contratos_vencidos' => $vencidos];
    }

    /**
     * RF-021: avisos 15, 7 y 1 día antes y el mismo día del mantenimiento (configurables).
     *
     * @return int envíos encolados
     */
    public function enviarAvisosMantenimiento(): int
    {
        $encolados = 0;

        foreach ((array) Configuracion::valor('notificaciones.dias_aviso') as $dias) {
            $dias = (int) $dias;
            $fecha = now()->addDays($dias)->toDateString();

            MantenimientoProgramado::pendientes()
                ->whereDate('fecha_programada', $fecha)
                ->with('equipo.edificio')
                ->each(function (MantenimientoProgramado $mantenimiento) use ($dias, &$encolados) {
                    $encolados += $this->notificaciones->enviar(
                        $this->notificaciones->destinatariosEquipo($mantenimiento->equipo),
                        new AvisoMantenimientoNotification($mantenimiento, 'aviso', $dias),
                        // La fecha forma parte del tipo: si se reprograma, se vuelve a avisar.
                        "aviso_{$dias}d_{$mantenimiento->fecha_programada->format('Ymd')}",
                        'mantenimiento',
                        $mantenimiento->id,
                    );
                });
        }

        return $encolados;
    }

    /**
     * RF-020: alerta por vencimiento del contrato N días antes (30 por defecto). Se envía una vez.
     */
    public function alertarVencimientoContratos(): int
    {
        $dias = (int) Configuracion::valor('contratos.dias_alerta_vencimiento');
        $limite = now()->addDays($dias)->toDateString();
        $encolados = 0;

        Contrato::with('cliente')
            ->where('estado', 'vigente')
            ->whereNull('alerta_vencimiento_en')
            ->whereDate('fecha_fin', '<=', $limite)
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->each(function (Contrato $contrato) use (&$encolados) {
                $encolados += $this->notificaciones->enviar(
                    $this->notificaciones->destinatariosContratos(),
                    new AlertaVencimientoContratoNotification($contrato),
                    'vencimiento_contrato_'.$contrato->fecha_fin->format('Ymd'),
                    'contrato',
                    $contrato->id,
                );
                $contrato->update(['alerta_vencimiento_en' => now()]);
            });

        return $encolados;
    }
}
