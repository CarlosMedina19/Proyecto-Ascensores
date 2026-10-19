<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los avisos de SIGMA. Cada envío usa un solo canal ("database" o "mail"),
 * para registrar el estado de cada canal por separado (RF-021).
 */
abstract class NotificacionSigma extends Notification
{
    use SerializesModels;

    public string $canal = 'database';

    abstract public function titulo(): string;

    abstract public function mensaje(): string;

    /** Ruta del panel web donde se ve el detalle. */
    abstract public function enlace(): ?string;

    public function porCanal(string $canal): static
    {
        $copia = clone $this;
        $copia->canal = $canal;

        return $copia;
    }

    public function via(object $notificable): array
    {
        return [$this->canal];
    }

    public function toArray(object $notificable): array
    {
        return [
            'titulo' => $this->titulo(),
            'mensaje' => $this->mensaje(),
            'tipo' => class_basename(static::class),
            'enlace' => $this->enlace(),
        ];
    }

    public function toMail(object $notificable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject('SIGMA — '.$this->titulo())
            ->greeting('Hola, '.$notificable->nombre)
            ->line($this->mensaje())
            ->salutation('Equipo SIGMA');

        if ($this->enlace()) {
            $correo->action('Ver en SIGMA', config('sigma.url_frontend').$this->enlace());
        }

        return $correo;
    }
}
