<?php

namespace App\Policies;

use App\Models\MantenimientoProgramado;
use App\Models\Usuario;

class MantenimientoProgramadoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('mantenimientos.ver');
    }

    public function view(Usuario $usuario, MantenimientoProgramado $mantenimiento): bool
    {
        return $usuario->tienePermiso('mantenimientos.ver')
            && $usuario->puedeVerCliente($mantenimiento->equipo?->edificio?->cliente_id);
    }

    public function update(Usuario $usuario, MantenimientoProgramado $mantenimiento): bool
    {
        return $usuario->tienePermiso('mantenimientos.gestionar');
    }
}
