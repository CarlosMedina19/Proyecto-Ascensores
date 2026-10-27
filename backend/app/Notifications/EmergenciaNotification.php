<?php

namespace App\Notifications;

use App\Models\Emergencia;

/** Aviso inmediato de una emergencia y de sus cambios de estado. */
class EmergenciaNotification extends NotificacionSigma
{
    public function __construct(public Emergencia $emergencia, public string $evento = 'reportada') {}

    public function titulo(): string
    {
        $codigo = $this->emergencia->equipo?->codigo;

        return match ($this->evento) {
            'reportada' => $this->emergencia->personas_atrapadas
                ? "Personas atrapadas en {$codigo}"
                : "Emergencia en {$codigo}",
            'resuelta' => "Emergencia {$this->emergencia->numero} resuelta",
            default => "Emergencia {$this->emergencia->numero}: ".mb_strtolower(Emergencia::ESTADOS[$this->emergencia->estado] ?? $this->emergencia->estado),
        };
    }

    public function mensaje(): string
    {
        $e = $this->emergencia;
        $lugar = $e->equipo?->ubicacion ?: $e->equipo?->edificio?->nombre;

        return match ($this->evento) {
            'reportada' => sprintf(
                '%s (%s). %s Tiempo objetivo de llegada: %d minutos.',
                Emergencia::TIPOS[$e->tipo] ?? $e->tipo,
                $lugar,
                $e->descripcion ? rtrim(trim($e->descripcion), '.').'.' : '', // sin doble punto si ya termina en punto
                $e->minutos_objetivo,
            ),
            'resuelta' => "Se resolvió la emergencia del ascensor {$e->equipo?->codigo}. ".($e->solucion ?? ''),
            default => "El estado de la emergencia {$e->numero} cambió a: ".(Emergencia::ESTADOS[$e->estado] ?? $e->estado).'.',
        };
    }

    public function enlace(): ?string
    {
        return '/emergencias/'.$this->emergencia->id;
    }
}
