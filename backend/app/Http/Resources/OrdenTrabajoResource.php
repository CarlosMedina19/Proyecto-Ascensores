<?php

namespace App\Http\Resources;

use App\Models\ChecklistItem;
use App\Models\Tecnico;
use Illuminate\Http\Request;

class OrdenTrabajoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        $usuario = $solicitud->user();

        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'numero' => $this->numero,
            'servicio' => $this->servicio,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? ['id' => $this->cliente->id, 'nombre' => $this->cliente->nombre] : null),
            'edificio_id' => $this->edificio_id,
            'edificio' => $this->whenLoaded('edificio', fn () => $this->edificio ? [
                'id' => $this->edificio->id,
                'nombre' => $this->edificio->nombre,
                'direccion' => $this->edificio->direccion,
                'ciudad' => $this->edificio->ciudad,
            ] : null),
            'equipo_id' => $this->equipo_id,
            'equipo' => $this->whenLoaded('equipo', fn () => $this->equipo ? [
                'id' => $this->equipo->id,
                'codigo' => $this->equipo->codigo,
                'ubicacion' => $this->equipo->ubicacion,
                'estado' => $this->equipo->estado,
            ] : null),
            'mantenimiento_id' => $this->mantenimiento_id,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'hora' => $this->hora ? substr($this->hora, 0, 5) : null,
            'duracion_estimada' => $this->duracion_estimada,
            'fin_estimado' => $this->fecha ? $this->finProgramado()->format('H:i') : null,
            'prioridad' => $this->prioridad,
            'estado' => $this->estado,
            'descripcion' => $this->descripcion,
            'observaciones' => $this->observaciones,
            'informe' => $this->informe,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'inicio_en' => $this->inicio_en?->toIso8601String(),
            'fin_en' => $this->fin_en?->toIso8601String(),
            'duracion_minutos' => $this->duracion_minutos,
            'tecnicos' => $this->whenLoaded('tecnicos', fn () => $this->tecnicos->map(fn (Tecnico $t) => [
                'id' => $t->id,
                'usuario_id' => $t->usuario_id,
                'nombre' => $t->usuario?->nombre,
                'telefono' => $t->telefono,
                'especialidades' => $t->relationLoaded('especialidades') ? $t->especialidades->pluck('nombre')->values() : null,
                'asignado_en' => $t->pivot?->asignado_en,
            ])->values()),
            'creado_por' => $this->whenLoaded('creador', fn () => $this->creador ? ['id' => $this->creador->id, 'nombre' => $this->creador->nombre] : null),
            'trazabilidad' => TrazabilidadOrdenResource::collection($this->whenLoaded('trazabilidad')),
            // Fase 4: planilla (resumen), firmas, informes y correctivos derivados.
            'lleva_planilla' => $this->llevaPlanilla(),
            'planilla' => $this->whenLoaded('planilla', fn () => $this->resumenPlanilla()),
            'firmas' => FirmaOrdenResource::collection($this->whenLoaded('firmas')),
            'informes' => InformeOrdenResource::collection($this->whenLoaded('informes')),
            'orden_origen' => $this->whenLoaded('ordenOrigen', fn () => $this->ordenOrigen
                ? ['id' => $this->ordenOrigen->id, 'numero' => $this->ordenOrigen->numero, 'item' => $this->resultadoOrigen?->item?->nombre]
                : null),
            'correctivos_derivados' => $this->whenLoaded('correctivosDerivados', fn () => $this->correctivosDerivados
                ->map(fn ($c) => ['id' => $c->id, 'numero' => $c->numero, 'estado' => $c->estado, 'prioridad' => $c->prioridad])
                ->values()),
            // Acciones que el usuario actual puede hacer (la API las vuelve a validar).
            'permisos' => $this->when($usuario !== null, fn () => [
                'editar' => $usuario->can('update', $this->resource),
                'asignar' => $usuario->can('asignar', $this->resource),
                'cancelar' => $usuario->can('cancelar', $this->resource),
                'ejecutar' => $this->relationLoaded('tecnicos')
                    ? $usuario->tienePermiso('ordenes.ejecutar') && $this->tecnicos->contains('usuario_id', $usuario->id)
                    : false,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Avance de la planilla para mostrar en la orden sin cargar todos los ítems. */
    private function resumenPlanilla(): array
    {
        $total = ChecklistItem::where('activo', true)->count();
        $resultados = $this->planilla?->resultados ?? collect();

        return [
            'iniciada' => $this->planilla !== null,
            'total_items' => $total,
            'registrados' => $resultados->count(),
            'completa' => $this->planilla !== null && $resultados->count() >= $total,
            'buenos' => $resultados->where('estado', 'B')->count(),
            'regulares' => $resultados->where('estado', 'R')->count(),
            'malos' => $resultados->where('estado', 'M')->count(),
            'completada_en' => $this->planilla?->completada_en?->toIso8601String(),
        ];
    }
}
