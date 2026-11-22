<?php

namespace App\Notifications;

use App\Models\CodigoVerificacion;
use App\Models\Usuario;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Código de 6 dígitos para el segundo paso del inicio de sesión (se envía en el momento, sin cola). */
class CodigoVerificacionNotification extends Notification
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $proposito,
        public readonly int $minutos,
        public readonly ?string $ip = null,
    ) {}

    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notificable): MailMessage
    {
        $asunto = $this->proposito === CodigoVerificacion::RECUPERACION ? 'recuperar su contraseña' : 'iniciar sesión';

        return (new MailMessage)
            ->subject("SIGMA — Su código de verificación: {$this->codigo}")
            ->greeting("Hola, {$notificable->nombre}")
            ->line("Use este código para {$asunto} en SIGMA:")
            ->line("**{$this->codigo}**")
            ->line("Vence en {$this->minutos} minutos y solo sirve una vez.".($this->ip ? " Solicitud desde la dirección {$this->ip}." : ''))
            ->line('Si usted no intentó entrar, cambie su contraseña: alguien la conoce.')
            ->salutation('Equipo SIGMA');
    }
}
