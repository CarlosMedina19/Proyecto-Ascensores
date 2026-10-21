<?php

namespace App\Notifications;

use App\Models\Certificacion;

/** Aviso de vencimiento de la certificación anual de un ascensor (60, 30 y 15 días antes). */
class AlertaCertificacionNotification extends NotificacionSigma
{
    public function __construct(public Certificacion $certificacion, public int $dias) {}

    public function titulo(): string
    {
        return "Certificación de {$this->certificacion->equipo->codigo} por vencer";
    }

    public function mensaje(): string
    {
        $fecha = $this->certificacion->fecha_vencimiento->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $plazo = $this->dias === 1 ? '1 día' : "{$this->dias} días";

        return "La revisión anual del ascensor {$this->certificacion->equipo->codigo} vence el {$fecha} (en {$plazo}). "
            .'Programe la inspección con un organismo acreditado por ONAC para no dejar el ascensor sin certificado.';
    }

    public function enlace(): ?string
    {
        return '/equipos/'.$this->certificacion->equipo_id;
    }
}
