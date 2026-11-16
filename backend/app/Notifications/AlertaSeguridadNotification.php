<?php

namespace App\Notifications;

use App\Models\Usuario;

/**
 * Aviso de seguridad: una cuenta se bloqueó por intentos fallidos de inicio de sesión.
 * Lo reciben los administradores y el dueño de la cuenta, para que reaccionen a tiempo si no fue él.
 */
class AlertaSeguridadNotification extends NotificacionSigma
{
    public function __construct(
        public Usuario $cuenta,
        public int $intentos,
        public int $minutosBloqueo,
        public ?string $ip,
    ) {}

    public function titulo(): string
    {
        return 'Cuenta bloqueada por intentos fallidos';
    }

    public function mensaje(): string
    {
        $origen = $this->ip ? " desde la dirección {$this->ip}" : '';

        return "La cuenta {$this->cuenta->correo} se bloqueó {$this->minutosBloqueo} minutos tras {$this->intentos} "
            ."intentos fallidos de inicio de sesión{$origen}. Si no fue el dueño de la cuenta, cambie la contraseña "
            .'y revise la auditoría.';
    }

    public function enlace(): ?string
    {
        return '/auditoria';
    }
}
