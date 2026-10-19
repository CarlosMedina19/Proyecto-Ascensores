<?php

namespace App\Notifications;

use App\Models\Contrato;

/**
 * RF-020: alerta por vencimiento del contrato (30 días antes, configurable).
 */
class AlertaVencimientoContratoNotification extends NotificacionSigma
{
    public function __construct(public Contrato $contrato) {}

    public function titulo(): string
    {
        return "Contrato {$this->contrato->numero} por vencer";
    }

    public function mensaje(): string
    {
        $dias = max(0, $this->contrato->diasParaVencer());
        $fecha = $this->contrato->fecha_fin->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        return "El contrato {$this->contrato->numero} de {$this->contrato->cliente?->nombre} vence el {$fecha} "
            ."(en {$dias} días). Registre el seguimiento de la renovación.";
    }

    public function enlace(): ?string
    {
        return '/contratos/'.$this->contrato->id;
    }
}
