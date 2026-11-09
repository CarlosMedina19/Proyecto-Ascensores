<?php

namespace App\Notifications;

use App\Models\OrdenTrabajo;

/**
 * Avisos de órdenes de trabajo: asignación al técnico (RF-024), prioridad crítica al
 * coordinador (RF-027), reprogramación y cancelación.
 */
class OrdenTrabajoNotification extends NotificacionSigma
{
    public const SERVICIOS = ['preventivo' => 'preventivo', 'correctivo' => 'correctivo', 'inspeccion' => 'inspección'];

    public function __construct(
        public OrdenTrabajo $orden,
        public string $evento,
        public ?string $detalle = null,
    ) {}

    private function descripcion(): string
    {
        $equipo = $this->orden->equipo?->codigo;
        $edificio = $this->orden->edificio?->nombre;
        $fecha = $this->orden->fecha->locale('es')->isoFormat('dddd D [de] MMMM');
        $hora = substr((string) $this->orden->hora, 0, 5);
        $servicio = self::SERVICIOS[$this->orden->servicio] ?? $this->orden->servicio;

        return "Orden {$this->orden->numero} ({$servicio}) del ascensor {$equipo} en {$edificio}, el {$fecha} a las {$hora}.";
    }

    public function titulo(): string
    {
        return match ($this->evento) {
            'asignada' => "Nueva orden asignada · {$this->orden->numero}",
            'critica' => "Orden con prioridad crítica · {$this->orden->numero}",
            'reprogramada' => "Orden reprogramada · {$this->orden->numero}",
            'cancelada' => "Orden cancelada · {$this->orden->numero}",
            'iniciada' => "Mantenimiento iniciado · {$this->orden->equipo?->codigo}",
            'finalizada' => "Mantenimiento finalizado · {$this->orden->equipo?->codigo}",
            default => "Orden {$this->orden->numero}",
        };
    }

    public function mensaje(): string
    {
        return match ($this->evento) {
            'asignada' => 'Se le asignó la '.lcfirst($this->descripcion()),
            'critica' => 'Requiere atención inmediata: '.lcfirst($this->descripcion()),
            'cancelada' => 'Se canceló la '.lcfirst($this->descripcion()).' Motivo: '.$this->orden->motivo_cancelacion,
            'iniciada' => "{$this->detalle} inició el mantenimiento del ascensor {$this->orden->equipo?->codigo} en {$this->orden->edificio?->nombre} (orden {$this->orden->numero}).",
            'finalizada' => "Se finalizó la orden {$this->orden->numero} del ascensor {$this->orden->equipo?->codigo} en {$this->orden->edificio?->nombre}"
                ." ({$this->orden->duracion_minutos} min). El informe técnico en PDF está en la orden."
                .($this->orden->informe ? " Trabajo realizado: {$this->orden->informe}" : ''),
            default => $this->descripcion(),
        };
    }

    public function enlace(): ?string
    {
        return "/ordenes/{$this->orden->id}";
    }
}
