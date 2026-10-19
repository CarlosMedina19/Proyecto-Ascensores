<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MantenimientoProgramadoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        $equipo = $this->relationLoaded('equipo') ? $this->equipo : null;
        $edificio = $equipo?->relationLoaded('edificio') ? $equipo->edificio : null;
        $cliente = $edificio?->relationLoaded('cliente') ? $edificio->cliente : null;
        $asignacion = $this->relationLoaded('asignacion') ? $this->asignacion : null;

        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'asignacion_id' => $this->asignacion_id,
            'equipo_id' => $this->equipo_id,
            'equipo' => $this->whenLoaded('equipo', fn () => [
                'id' => $equipo->id,
                'codigo' => $equipo->codigo,
                'edificio' => $edificio ? [
                    'id' => $edificio->id,
                    'nombre' => $edificio->nombre,
                    'cliente' => $cliente ? ['id' => $cliente->id, 'nombre' => $cliente->nombre] : null,
                ] : null,
            ]),
            'plan' => $this->when(
                $asignacion?->relationLoaded('plan'),
                fn () => $asignacion->plan ? ['id' => $asignacion->plan->id, 'nombre' => $asignacion->plan->nombre] : null
            ),
            'periodicidad' => $this->when(
                $asignacion?->relationLoaded('periodicidad'),
                fn () => new PeriodicidadResource($asignacion->periodicidad)
            ),
            'fecha_programada' => $this->fecha_programada?->format('Y-m-d'),
            'fecha_teorica' => $this->fecha_teorica?->format('Y-m-d'),
            'hora' => $this->hora ? substr($this->hora, 0, 5) : null,
            'precio' => $this->precio,
            'estado' => $this->estado,
            'fecha_ejecucion' => $this->fecha_ejecucion?->format('Y-m-d'),
            'motivo' => $this->motivo,
            'observacion' => $this->observacion,
            'orden_activa' => $this->whenLoaded('ordenActiva', fn () => $this->ordenActiva
                ? ['id' => $this->ordenActiva->id, 'numero' => $this->ordenActiva->numero, 'estado' => $this->ordenActiva->estado]
                : null),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
