<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ClienteResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'nit' => $this->nit,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'regimen_tributario' => $this->regimen_tributario,
            'actividad_economica' => $this->actividad_economica,
            'estado' => (bool) $this->estado,
            'observaciones' => $this->observaciones,
            'contactos' => ContactoClienteResource::collection($this->whenLoaded('contactos')),
            'edificios_count' => $this->whenCounted('edificios'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
