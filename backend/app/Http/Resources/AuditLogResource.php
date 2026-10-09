<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'action' => $this->accion,
            'model' => class_basename($this->modelo),
            'model_id' => $this->modelo_id,
            'changes' => $this->cambios,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
