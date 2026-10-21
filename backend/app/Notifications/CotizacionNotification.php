<?php

namespace App\Notifications;

use App\Models\Cotizacion;

/** Aviso al cliente de una cotización nueva y al personal cuando el cliente responde. */
class CotizacionNotification extends NotificacionSigma
{
    public function __construct(public Cotizacion $cotizacion, public string $evento = 'enviada') {}

    public function titulo(): string
    {
        return match ($this->evento) {
            'aprobada' => "Cotización {$this->cotizacion->numero} aprobada",
            'rechazada' => "Cotización {$this->cotizacion->numero} rechazada",
            default => "Nueva cotización {$this->cotizacion->numero}",
        };
    }

    public function mensaje(): string
    {
        $c = $this->cotizacion;
        $total = '$ '.number_format((float) $c->total, 0, ',', '.');

        return match ($this->evento) {
            'aprobada' => "{$c->cliente?->nombre} aprobó la cotización «{$c->titulo}» por {$total}. Programe la orden de trabajo.",
            'rechazada' => "{$c->cliente?->nombre} rechazó la cotización «{$c->titulo}». Motivo: {$c->motivo_rechazo}",
            default => "Tiene una cotización por {$total} para el ascensor {$c->equipo?->codigo}: «{$c->titulo}». "
                .'Revísela y apruébela o recházela antes del '.$c->vigente_hasta->format('d/m/Y').'.',
        };
    }

    public function enlace(): ?string
    {
        return '/cotizaciones/'.$this->cotizacion->id;
    }
}
