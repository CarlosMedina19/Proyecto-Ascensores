<?php

namespace App\Notifications;

use App\Models\OrdenTrabajo;

/**
 * RF-031 a RF-036: ítems de la planilla en estado M (Malo). Los de seguridad o emergencia se
 * avisan de inmediato; los demás, al finalizar la orden.
 */
class ChecklistNotification extends NotificacionSigma
{
    /**
     * @param  array<int, string>  $items  "Sección · ítem: observación"
     */
    public function __construct(
        public OrdenTrabajo $orden,
        public array $items,
        public bool $inmediato,
    ) {}

    public function titulo(): string
    {
        $cantidad = count($this->items);
        $equipo = $this->orden->equipo?->codigo;

        return $this->inmediato
            ? "Falla de seguridad en {$equipo} · {$this->orden->numero}"
            : ($cantidad === 1 ? "1 ítem en estado Malo en {$equipo}" : "{$cantidad} ítems en estado Malo en {$equipo}");
    }

    public function mensaje(): string
    {
        $lugar = $this->orden->edificio?->nombre;

        return ($this->inmediato ? 'Requiere atención inmediata. ' : '')
            ."Planilla de la orden {$this->orden->numero} ({$lugar}): ".implode('; ', $this->items).'.';
    }

    public function enlace(): ?string
    {
        return "/ordenes/{$this->orden->id}";
    }
}
