<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AsignacionPlanResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'equipo_id' => $this->equipo_id,
            'equipo' => new EquipoResource($this->whenLoaded('equipo')),
            'plan_id' => $this->plan_id,
            'plan' => new PlanMantenimientoResource($this->whenLoaded('plan')),
            'periodicidad_id' => $this->periodicidad_id,
            'periodicidad' => new PeriodicidadResource($this->whenLoaded('periodicidad')),
            'contrato_id' => $this->contrato_id,
            'contrato' => $this->whenLoaded('contrato', fn () => $this->contrato ? [
                'id' => $this->contrato->id,
                'numero' => $this->contrato->numero,
                'estado' => $this->contrato->estado,
                'fecha_fin' => $this->contrato->fecha_fin?->format('Y-m-d'),
            ] : null),
            'precio' => $this->precio,
            'aplica_iva' => (bool) $this->aplica_iva,
            'porcentaje_iva' => $this->porcentaje_iva,
            'precio_con_iva' => $this->precioConIva(),
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'hora_preferida' => $this->hora_preferida ? substr($this->hora_preferida, 0, 5) : null,
            'estado' => $this->estado,
            'ultimo_mantenimiento' => $this->ultimo_mantenimiento?->format('Y-m-d'),
            'proximo_mantenimiento' => $this->proximo_mantenimiento?->format('Y-m-d'),
            'mantenimientos' => MantenimientoProgramadoResource::collection($this->whenLoaded('mantenimientos')),
            'cambios' => CambioAsignacionResource::collection($this->whenLoaded('cambios')),
            'historial_precios' => HistorialPrecioResource::collection($this->whenLoaded('historialPrecios')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
