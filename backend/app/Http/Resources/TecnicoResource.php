<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * RF-022: técnico = usuario de rol técnico + su perfil (puede faltar si se creó desde Usuarios).
 */
class TecnicoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        $perfil = $this->relationLoaded('tecnico') ? $this->tecnico : null;

        return [
            'usuario_id' => $this->id,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'activo' => (bool) $this->activo,
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->toIso8601String(),
            'perfil_completo' => $perfil !== null,
            'id' => $perfil?->id,
            'tipo_documento' => $perfil?->tipo_documento,
            'numero_documento' => $perfil?->numero_documento,
            'telefono' => $perfil?->telefono,
            'disponibilidad' => $perfil?->disponibilidad,
            'motivo_no_disponible' => $perfil?->motivo_no_disponible,
            'no_disponible_hasta' => $perfil?->no_disponible_hasta?->format('Y-m-d'),
            'observaciones' => $perfil?->observaciones,
            'puede_recibir_ordenes' => (bool) ($perfil?->puedeRecibirOrdenes()),
            'especialidades' => $perfil && $perfil->relationLoaded('especialidades')
                ? $perfil->especialidades->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre])->values()
                : [],
            'ordenes_activas' => $this->when(isset($this->ordenes_activas), fn () => $this->ordenes_activas),
        ];
    }
}
