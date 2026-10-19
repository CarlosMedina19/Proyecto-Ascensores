<?php

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * RF-001: el enlace de recuperación vence a los 60 minutos y es de un solo uso.
 */
class RestablecerContrasenaNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notificable): MailMessage
    {
        $enlace = config('sigma.url_frontend').'/restablecer-contrasena?'.http_build_query([
            'token' => $this->token,
            'correo' => $notificable->correo,
        ]);

        $minutos = config('auth.passwords.usuarios.expire');

        return (new MailMessage)
            ->subject('SIGMA — Recuperación de contraseña')
            ->greeting("Hola, {$notificable->nombre}")
            ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta en SIGMA.')
            ->action('Restablecer contraseña', $enlace)
            ->line("Este enlace vence en {$minutos} minutos y solo puede usarse una vez.")
            ->line('Si usted no hizo esta solicitud, ignore este mensaje.')
            ->salutation('Equipo SIGMA');
    }
}
