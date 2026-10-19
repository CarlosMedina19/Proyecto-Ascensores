<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ContratoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'numero' => $this->numero,
            'cliente_id' => $this->cliente_id,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'contrato_anterior_id' => $this->contrato_anterior_id,
            'contrato_anterior' => $this->whenLoaded('contratoAnterior', fn () => $this->contratoAnterior
                ? ['id' => $this->contratoAnterior->id, 'numero' => $this->contratoAnterior->numero]
                : null),
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'dias_para_vencer' => $this->estado === 'vigente' ? $this->diasParaVencer() : null,
            'valor' => $this->valor,
            'servicios' => $this->servicios,
            'condiciones' => $this->condiciones,
            'estado' => $this->estado,
            'estado_renovacion' => $this->estado_renovacion,
            'equipos_count' => $this->whenCounted('equipos'),
            'equipos' => EquipoResource::collection($this->whenLoaded('equipos')),
            'seguimientos' => SeguimientoRenovacionResource::collection($this->whenLoaded('seguimientos')),
            'asignaciones' => AsignacionPlanResource::collection($this->whenLoaded('asignaciones')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
