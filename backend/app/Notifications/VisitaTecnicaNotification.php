<?php

namespace App\Notifications;

use App\Models\VisitaTecnica;

/** Planilla de asistencia técnica registrada: llega al administrador y a los usuarios del cliente. */
class VisitaTecnicaNotification extends NotificacionSigma
{
    public function __construct(public VisitaTecnica $visita) {}

    public function titulo(): string
    {
        return "Visita técnica registrada en {$this->visita->equipo?->codigo} · N.º {$this->visita->numero}";
    }

    public function mensaje(): string
    {
        $conteo = $this->visita->conteo();
        $malos = $conteo['M'] > 0 ? " Atención: {$conteo['M']} actividad(es) en estado Malo." : '';

        return ($this->visita->tecnico?->nombre ?? 'El técnico')
            .' diligenció la planilla de asistencia técnica en '.($this->visita->edificio?->nombre ?? 'el edificio')
            .": {$conteo['B']} B, {$conteo['R']} R, {$conteo['M']} M.{$malos} El PDF está en la ficha del ascensor.";
    }

    public function enlace(): ?string
    {
        return "/equipos/{$this->visita->equipo_id}";
    }
}
