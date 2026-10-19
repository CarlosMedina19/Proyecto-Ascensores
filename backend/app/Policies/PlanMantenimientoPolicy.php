<?php

namespace App\Policies;

use App\Models\PlanMantenimiento;
use App\Models\Usuario;

class PlanMantenimientoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('planes.ver');
    }

    public function view(Usuario $usuario, PlanMantenimiento $plan): bool
    {
        return $usuario->tienePermiso('planes.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('planes.crear');
    }

    public function update(Usuario $usuario, PlanMantenimiento $plan): bool
    {
        return $usuario->tienePermiso('planes.editar');
    }

    /** RF-015: solo el contador y el administrador cambian precios. */
    public function cambiarPrecios(Usuario $usuario, PlanMantenimiento $plan): bool
    {
        return $usuario->tienePermiso('precios.editar');
    }

    public function delete(Usuario $usuario, PlanMantenimiento $plan): bool
    {
        return $usuario->tienePermiso('planes.eliminar');
    }
}
