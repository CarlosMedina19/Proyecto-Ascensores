<?php

namespace App\Notifications;

/** Alerta de operación (cola detenida, respaldo atrasado, servicio caído…) para los administradores. */
class AlertaOperacionNotification extends NotificacionSigma
{
    public function __construct(public string $problema, public string $detalle) {}

    public function titulo(): string
    {
        return "Alerta del sistema: {$this->problema}";
    }

    public function mensaje(): string
    {
        return $this->detalle;
    }

    public function enlace(): ?string
    {
        return '/auditoria';
    }
}
