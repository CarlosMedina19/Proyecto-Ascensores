<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Models\Usuario;

class ContratoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('contratos.ver');
    }

    public function view(Usuario $usuario, Contrato $contrato): bool
    {
        return $usuario->tienePermiso('contratos.ver') && $usuario->puedeVerCliente($contrato->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('contratos.crear');
    }

    public function update(Usuario $usuario, Contrato $contrato): bool
    {
        return $usuario->tienePermiso('contratos.editar');
    }

    public function renovar(Usuario $usuario, Contrato $contrato): bool
    {
        return $usuario->tienePermiso('contratos.renovacion');
    }
}
