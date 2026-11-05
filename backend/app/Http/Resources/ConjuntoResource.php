<?php

namespace App\Http\Resources;

use App\Models\Edificio;
use Illuminate\Http\Request;

/** @mixin \App\Models\Conjunto */
class ConjuntoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'tipo' => $this->tipo,
            'tipo_texto' => Edificio::TIPOS[$this->tipo] ?? $this->tipo,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'departamento' => $this->departamento,
            'codigo_postal' => $this->codigo_postal,
            'nombre_contacto' => $this->nombre_contacto,
            'telefono_contacto' => $this->telefono_contacto,
            'correo_contacto' => $this->correo_contacto,
            'observaciones' => $this->observaciones,
            'activo' => (bool) $this->activo,
            'edificios_count' => $this->whenCounted('edificios'),
            'equipos_count' => $this->whenCounted('equipos'),
            'edificios' => EdificioResource::collection($this->whenLoaded('edificios')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
