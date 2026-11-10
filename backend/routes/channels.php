<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

// Tiempo real solo en Auditoría (registros y mapa de técnicos) y en las notificaciones de cada usuario.
Broadcast::channel('auditoria', fn (Usuario $usuario) => $usuario->activo && $usuario->tienePermiso('registros_auditoria.ver'));

Broadcast::channel('usuarios.{id}', fn (Usuario $usuario, int $id) => $usuario->id === $id && $usuario->activo);
