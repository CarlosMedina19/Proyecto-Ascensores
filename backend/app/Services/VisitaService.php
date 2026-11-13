<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;

/**
 * Cuándo un técnico puede registrar una visita a un ascensor. Debe cumplirse al menos una condición:
 * - el ascensor tiene un mantenimiento activo (orden en camino, en proceso o pendiente);
 * - tiene un mantenimiento programado vencido o para los próximos días;
 * - tiene una emergencia reportada que sigue abierta.
 * Los demás roles con permiso de historial no tienen esta restricción.
 */
class VisitaService
{
    /** Días de anticipación con que se puede registrar la visita de un mantenimiento programado. */
    public const DIAS_ANTICIPACION = 2;

    private const ESTADOS_ORDEN_ACTIVA = ['en_camino', 'en_proceso', 'pendiente'];

    /** @return array{permitida: bool, motivo: string} */
    public function habilitacion(Equipo $equipo, Usuario $usuario): array
    {
        if (! $usuario->esTecnico()) {
            return ['permitida' => true, 'motivo' => 'Su rol puede registrar visitas en cualquier momento.'];
        }

        $orden = OrdenTrabajo::where('equipo_id', $equipo->id)->whereIn('estado', self::ESTADOS_ORDEN_ACTIVA)->orderByDesc('id')->first();
        if ($orden) {
            return ['permitida' => true, 'motivo' => "Mantenimiento activo: orden {$orden->numero}."];
        }

        $emergencia = Emergencia::where('equipo_id', $equipo->id)->whereIn('estado', ['reportada', 'asignada', 'en_sitio'])->orderByDesc('id')->first();
        if ($emergencia) {
            return ['permitida' => true, 'motivo' => 'Emergencia reportada el '.$emergencia->reportada_en?->format('d/m/Y H:i').'.'];
        }

        $visita = MantenimientoProgramado::where('equipo_id', $equipo->id)
            ->whereIn('estado', MantenimientoProgramado::PENDIENTES)
            ->whereDate('fecha_programada', '<=', now()->addDays(self::DIAS_ANTICIPACION)->toDateString())
            ->orderBy('fecha_programada')
            ->first();
        if ($visita) {
            $vencida = $visita->fecha_programada->isPast() && ! $visita->fecha_programada->isToday();

            return ['permitida' => true, 'motivo' => ($vencida ? 'Mantenimiento vencido del ' : 'Mantenimiento programado para el ').$visita->fecha_programada->format('d/m/Y').'.'];
        }

        $proxima = MantenimientoProgramado::where('equipo_id', $equipo->id)->whereIn('estado', MantenimientoProgramado::PENDIENTES)->orderBy('fecha_programada')->first()?->fecha_programada;

        return [
            'permitida' => false,
            'motivo' => 'Solo puede registrar la visita con un mantenimiento activo, una emergencia reportada o cuando falten '.self::DIAS_ANTICIPACION.' días o menos para el mantenimiento programado.'
                .($proxima ? ' El próximo es el '.$proxima->format('d/m/Y').'.' : ' Este ascensor no tiene mantenimientos programados.'),
        ];
    }

    public function exigir(Equipo $equipo, Usuario $usuario): void
    {
        $habilitacion = $this->habilitacion($equipo, $usuario);
        if (! $habilitacion['permitida']) {
            throw new ReglaNegocioException($habilitacion['motivo']);
        }
    }
}
