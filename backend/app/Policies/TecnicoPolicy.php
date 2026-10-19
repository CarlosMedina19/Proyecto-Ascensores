<?php

namespace App\Policies;

use App\Models\Usuario;

/**
 * RF-022: técnicos. Registrarlos crea una cuenta de usuario (exclusivo del administrador);
 * el coordinador actualiza especialidades, disponibilidad y estado.
 */
class TecnicoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('tecnicos.ver');
    }

    /** Ver un técnico: con permiso, o el propio técnico su perfil. */
    public function view(Usuario $usuario, Usuario $tecnico): bool
    {
        return $usuario->tienePermiso('tecnicos.ver') || $usuario->id === $tecnico->id;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('tecnicos.crear');
    }

    public function update(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('tecnicos.editar');
    }
}
