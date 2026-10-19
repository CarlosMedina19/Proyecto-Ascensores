<?php

namespace App\Http\Resources;

use App\Models\ChecklistItem;
use Illuminate\Http\Request;

/**
 * RF-030 a RF-038: planilla con el resultado de cada ítem, sus correctivos y las fotos.
 */
class PlanillaResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'orden_id' => $this->orden_id,
            'tecnico' => $this->whenLoaded('tecnico', fn () => $this->tecnico ? ['id' => $this->tecnico->id, 'nombre' => $this->tecnico->nombre] : null),
            'recomendaciones' => $this->recomendaciones,
            'iniciada_en' => $this->iniciada_en?->toIso8601String(),
            'completada_en' => $this->completada_en?->toIso8601String(),
            'resultados' => $this->whenLoaded('resultados', fn () => $this->resultados->map(fn ($r) => [
                'id' => $r->id,
                'item_id' => $r->item_id,
                'seccion' => $r->item?->seccion,
                'resultado' => $r->resultado,
                'estado' => $r->estado,
                'observacion' => $r->observacion,
                'notificado_en' => $r->notificado_en?->toIso8601String(),
                'correctivo_abierto' => $r->relationLoaded('correctivoAbierto') && $r->correctivoAbierto
                    ? ['id' => $r->correctivoAbierto->id, 'numero' => $r->correctivoAbierto->numero, 'estado' => $r->correctivoAbierto->estado]
                    : null,
                'correctivos' => $r->relationLoaded('correctivos') ? $r->correctivos->map(fn ($c) => ['id' => $c->id, 'numero' => $c->numero, 'estado' => $c->estado])->values() : [],
            ])->values()),
            'fotos' => $this->whenLoaded('fotos', fn () => $this->fotos->map(fn ($f) => [
                'id' => $f->id,
                'item_id' => $f->item_id,
                'nombre' => $f->nombre_original,
                'descripcion' => $f->descripcion,
                'ruta_archivo' => "/ordenes/{$this->orden_id}/planilla/fotos/{$f->id}/archivo",
            ])->values()),
            'secciones' => ChecklistItem::SECCIONES,
        ];
    }
}
