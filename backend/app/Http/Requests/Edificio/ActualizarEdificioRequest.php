<?php

namespace App\Http\Requests\Edificio;

use App\Models\Edificio;

class ActualizarEdificioRequest extends GuardarEdificioRequest
{
    protected function edificioActual(): ?Edificio
    {
        return $this->route('edificio');
    }
}
