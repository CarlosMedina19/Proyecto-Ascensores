<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ContactoClienteResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'cargo' => $this->cargo,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
        ];
    }
}
