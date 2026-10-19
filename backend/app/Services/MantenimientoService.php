<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\MantenimientoProgramado;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Agenda de mantenimientos programados: consulta, reprogramación, cancelación y ejecución.
 */
class MantenimientoService
{
    public function __construct(
        private readonly ProgramacionService $programacion,
        private readonly CalendarioHabilService $calendario,
        private readonly NotificacionService $notificaciones,
    ) {}

    /** Mantenimientos de un rango de fechas (calendario). */
    public function listar(array $filtros): Collection
    {
        $consulta = MantenimientoProgramado::with(['equipo.edificio.cliente', 'asignacion.plan', 'asignacion.periodicidad', 'ordenActiva'])
            ->whereBetween('fecha_programada', [$filtros['desde'], $filtros['hasta']]);
        $usuario = Auth::user();

        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', $usuario->cliente_id));
        }

        if (! empty($filtros['equipo_id'])) {
            $consulta->where('equipo_id', $filtros['equipo_id']);
        }
        if (! empty($filtros['cliente_id'])) {
            $consulta->whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', $filtros['cliente_id']));
        }
        // Fase 3: el calendario muestra la orden en lugar de la visita que ya tiene orden.
        if (! empty($filtros['sin_orden'])) {
            $consulta->whereDoesntHave('ordenActiva');
        }
        if (! empty($filtros['estado'])) {
            $consulta->whereIn('estado', (array) $filtros['estado']);
        } else {
            $consulta->where('estado', '!=', 'cancelado');
        }

        return $consulta->orderBy('fecha_programada')->orderBy('hora')->orderBy('equipo_id')->get();
    }

    /**
     * RF-018: reprogramar actualiza la fecha y deja registrado el cambio y el motivo.
     */
    public function reprogramar(MantenimientoProgramado $mantenimiento, string $fecha, string $motivo, ?string $hora = null): MantenimientoProgramado
    {
        $this->exigirPendiente($mantenimiento);
        $dia = CarbonImmutable::parse($fecha);
        $asignacion = $mantenimiento->asignacion;

        if ($dia->lt(now()->startOfDay())) {
            throw new ReglaNegocioException('No se puede reprogramar a una fecha pasada.');
        }
        if (! $this->calendario->esHabil($dia)) {
            throw new ReglaNegocioException('La nueva fecha cae en domingo o festivo. Elija un día hábil.');
        }
        if ($dia->gt($this->programacion->fechaLimite($asignacion))) {
            throw new ReglaNegocioException('La nueva fecha está fuera de la vigencia del plan.');
        }

        return DB::transaction(function () use ($mantenimiento, $dia, $motivo, $hora) {
            $anterior = $mantenimiento->fecha_programada->toDateString();
            $mantenimiento->update([
                'fecha_programada' => $dia->toDateString(),
                'hora' => $hora ?? $mantenimiento->hora,
                'estado' => 'reprogramado',
                'motivo' => $motivo,
                'actualizado_por' => Auth::id(),
            ]);
            $this->programacion->actualizarProximo($mantenimiento->asignacion);

            RegistroAuditoriaService::registrar('reprogramado', $mantenimiento, [
                'fecha_anterior' => $anterior,
                'fecha_nueva' => $dia->toDateString(),
                'motivo' => $motivo,
            ]);
            $this->notificaciones->eventoMantenimiento($mantenimiento, 'reprogramado');

            return $mantenimiento->fresh(['equipo.edificio.cliente', 'asignacion.plan', 'asignacion.periodicidad']);
        });
    }

    public function cancelar(MantenimientoProgramado $mantenimiento, string $motivo): MantenimientoProgramado
    {
        $this->exigirPendiente($mantenimiento);

        return DB::transaction(function () use ($mantenimiento, $motivo) {
            $mantenimiento->update(['estado' => 'cancelado', 'motivo' => $motivo, 'actualizado_por' => Auth::id()]);
            $this->programacion->actualizarProximo($mantenimiento->asignacion);

            RegistroAuditoriaService::registrar('cancelado', $mantenimiento, ['motivo' => $motivo]);
            $this->notificaciones->eventoMantenimiento($mantenimiento, 'cancelado');

            return $mantenimiento->fresh(['equipo.edificio.cliente', 'asignacion.plan', 'asignacion.periodicidad']);
        });
    }

    /**
     * Registra la ejecución. RF-017: la próxima fecha se recalcula desde la fecha
     * del último mantenimiento ejecutado. RF-009: queda en el historial del equipo.
     * (En la fase 3 lo hará el cierre de la orden de trabajo.)
     */
    public function ejecutar(MantenimientoProgramado $mantenimiento, string $fecha, ?string $observacion): MantenimientoProgramado
    {
        $this->exigirPendiente($mantenimiento);
        $dia = CarbonImmutable::parse($fecha);

        if ($dia->gt(now()->endOfDay())) {
            throw new ReglaNegocioException('La fecha de ejecución no puede ser futura.');
        }

        return DB::transaction(function () use ($mantenimiento, $dia, $observacion) {
            $mantenimiento->update([
                'estado' => 'ejecutado',
                'fecha_ejecucion' => $dia->toDateString(),
                'observacion' => $observacion,
                'actualizado_por' => Auth::id(),
            ]);

            $asignacion = $mantenimiento->asignacion;
            $ultimo = $asignacion->ultimo_mantenimiento;
            if (! $ultimo || $dia->gt($ultimo)) {
                $asignacion->update(['ultimo_mantenimiento' => $dia->toDateString()]);
                $this->programacion->programar($asignacion, 'Recalculado tras la ejecución de un mantenimiento', $dia);
            } else {
                $this->programacion->actualizarProximo($asignacion);
            }

            $mantenimiento->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'mantenimiento_preventivo',
                'descripcion' => "Mantenimiento preventivo realizado el {$dia->toDateString()}."
                    .($observacion ? " {$observacion}" : ''),
            ]);

            RegistroAuditoriaService::registrar('ejecutado', $mantenimiento, [
                'fecha_ejecucion' => $dia->toDateString(),
                'observacion' => $observacion,
            ]);
            $this->notificaciones->eventoMantenimiento($mantenimiento, 'ejecutado');

            return $mantenimiento->fresh(['equipo.edificio.cliente', 'asignacion.plan', 'asignacion.periodicidad']);
        });
    }

    private function exigirPendiente(MantenimientoProgramado $mantenimiento): void
    {
        if (! $mantenimiento->estaPendiente()) {
            throw new ReglaNegocioException('El mantenimiento ya fue ejecutado o cancelado y no se puede modificar.');
        }

        // Fase 3: la visita con una orden en curso se gestiona desde la orden.
        $orden = $mantenimiento->ordenActiva()->value('numero');
        if ($orden) {
            throw new ReglaNegocioException("La visita tiene la orden de trabajo {$orden}. Gestiónela desde la orden.");
        }
    }
}
