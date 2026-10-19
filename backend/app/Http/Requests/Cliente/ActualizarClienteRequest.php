<?php

namespace App\Http\Requests\Cliente;

use App\Models\Cliente;

class ActualizarClienteRequest extends GuardarClienteRequest
{
    protected function clienteActual(): ?Cliente
    {
        return $this->route('cliente');
    }
}
