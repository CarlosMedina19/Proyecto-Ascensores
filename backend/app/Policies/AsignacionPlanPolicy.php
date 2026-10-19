<?php

namespace App\Policies;

use App\Models\AsignacionPlan;
use App\Models\Usuario;

class AsignacionPlanPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('asignaciones.ver');
    }

    public function view(Usuario $usuario, AsignacionPlan $asignacion): bool
    {
        return $usuario->tienePermiso('asignaciones.ver')
            && $usuario->puedeVerCliente($asignacion->equipo?->edificio?->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('asignaciones.crear');
    }

    public function update(Usuario $usuario, AsignacionPlan $asignacion): bool
    {
        return $usuario->tienePermiso('asignaciones.editar');
    }

    public function cambiarPrecio(Usuario $usuario, AsignacionPlan $asignacion): bool
    {
        return $usuario->tienePermiso('precios.editar');
    }
}
