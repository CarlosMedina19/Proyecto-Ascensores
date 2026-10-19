<?php

namespace App\Policies;

use App\Models\OrdenTrabajo;
use App\Models\Usuario;

/**
 * RF-023 a RF-026: el técnico solo ve y avanza las órdenes que tiene asignadas,
 * el cliente solo ve las suyas y el coordinador gestiona todas.
 */
class OrdenTrabajoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('ordenes.ver');
    }

    public function view(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        if (! $usuario->tienePermiso('ordenes.ver')) {
            return false;
        }
        if ($usuario->esTecnico()) {
            return $orden->tieneAsignado($usuario);
        }

        return $usuario->puedeVerCliente($orden->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('ordenes.crear');
    }

    /** Reprogramar y cambiar la prioridad (RF-027: solo coordinador y administrador). */
    public function update(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tienePermiso('ordenes.editar');
    }

    public function asignar(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tienePermiso('ordenes.asignar');
    }

    public function cancelar(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tienePermiso('ordenes.cancelar');
    }

    /** RF-038: el correctivo derivado lo crea el técnico asignado o quien gestiona órdenes. */
    public function crearCorrectivo(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tienePermiso('ordenes.crear') || $this->ejecutar($usuario, $orden);
    }

    /** RF-025/RF-026: los estados de trabajo los avanza un técnico asignado a la orden. */
    public function ejecutar(Usuario $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tienePermiso('ordenes.ejecutar') && $orden->tieneAsignado($usuario);
    }
}
