<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\Usuario;

class ClientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('clientes.ver');
    }

    public function view(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->tienePermiso('clientes.ver')
            && (! $usuario->tieneRol('cliente') || $usuario->cliente_id === $cliente->id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('clientes.crear');
    }

    public function update(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->tienePermiso('clientes.editar');
    }

    public function delete(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->tienePermiso('clientes.eliminar');
    }

    public function agregarContacto(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->tienePermiso('clientes.contactos.crear');
    }
}
