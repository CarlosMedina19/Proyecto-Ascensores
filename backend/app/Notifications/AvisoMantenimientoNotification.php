<?php

namespace App\Notifications;

use App\Models\MantenimientoProgramado;

/**
 * RF-021: avisos de mantenimiento 15, 7 y 1 día antes, el mismo día y en eventos posteriores
 * (reprogramado, cancelado, ejecutado).
 */
class AvisoMantenimientoNotification extends NotificacionSigma
{
    public function __construct(
        public MantenimientoProgramado $mantenimiento,
        public string $evento,
        public ?int $dias = null,
    ) {}

    private function equipo(): string
    {
        $equipo = $this->mantenimiento->equipo;
        $edificio = $equipo?->edificio?->nombre;

        return trim("ascensor {$equipo?->codigo}".($edificio ? " ({$edificio})" : ''));
    }

    private function fecha(): string
    {
        return $this->mantenimiento->fecha_programada->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    }

    public function titulo(): string
    {
        return match ($this->evento) {
            'aviso' => match ($this->dias) {
                0 => 'Mantenimiento programado para hoy',
                1 => 'Mantenimiento programado para mañana',
                default => "Mantenimiento en {$this->dias} días",
            },
            'reprogramado' => 'Mantenimiento reprogramado',
            'cancelado' => 'Mantenimiento cancelado',
            'ejecutado' => 'Mantenimiento realizado',
            default => 'Aviso de mantenimiento',
        };
    }

    public function mensaje(): string
    {
        return match ($this->evento) {
            'aviso' => "El mantenimiento preventivo del {$this->equipo()} está programado para el {$this->fecha()}.",
            'reprogramado' => "El mantenimiento del {$this->equipo()} se reprogramó para el {$this->fecha()}."
                .($this->mantenimiento->motivo ? " Motivo: {$this->mantenimiento->motivo}" : ''),
            'cancelado' => "Se canceló el mantenimiento del {$this->equipo()} previsto para el {$this->fecha()}."
                .($this->mantenimiento->motivo ? " Motivo: {$this->mantenimiento->motivo}" : ''),
            'ejecutado' => "Se registró el mantenimiento del {$this->equipo()} realizado el "
                .$this->mantenimiento->fecha_ejecucion?->locale('es')->isoFormat('D [de] MMMM [de] YYYY').'.',
            default => "Novedad en el mantenimiento del {$this->equipo()}.",
        };
    }

    public function enlace(): ?string
    {
        return '/equipos/'.$this->mantenimiento->equipo_id;
    }
}
