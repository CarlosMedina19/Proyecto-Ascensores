<?php

namespace App\Policies;

use App\Models\RegistroAuditoria;
use App\Models\Usuario;

class RegistroAuditoriaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('registros_auditoria.ver');
    }

    public function view(Usuario $usuario, RegistroAuditoria $auditLog): bool
    {
        return $usuario->tienePermiso('registros_auditoria.ver');
    }
}
