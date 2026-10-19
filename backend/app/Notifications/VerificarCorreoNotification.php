<?php

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * RF-001: verificación de la cuenta mediante un enlace firmado que vence en 24 horas.
 */
class VerificarCorreoNotification extends Notification
{
    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notificable): MailMessage
    {
        return (new MailMessage)
            ->subject('SIGMA — Verifique su cuenta')
            ->greeting("Hola, {$notificable->nombre}")
            ->line('Se creó una cuenta para usted en SIGMA, el Sistema Integral de Gestión y Mantenimiento de Ascensores.')
            ->action('Verificar mi correo', self::enlace($notificable))
            ->line('El enlace vence en 24 horas.')
            ->salutation('Equipo SIGMA');
    }

    public static function enlace(Usuario $usuario): string
    {
        return URL::temporarySignedRoute('verificacion.verificar', now()->addHours(24), [
            'usuario' => $usuario->id,
            'hash' => sha1($usuario->correo),
        ]);
    }
}
