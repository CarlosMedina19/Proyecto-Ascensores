<?php

namespace App\Notifications;

use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Services\InventarioService;
use Illuminate\Support\Collection;

/** Avisos de inventario: stock mínimo (RF-043) y faltantes al finalizar una orden (RF-042). */
class InventarioNotification extends NotificacionSigma
{
    public function __construct(
        public string $evento,
        public Repuesto $repuesto,
        public ?OrdenTrabajo $orden = null,
        public ?Collection $faltantes = null,
    ) {}

    public function titulo(): string
    {
        return $this->evento === 'faltante'
            ? "Faltan repuestos para la orden {$this->orden?->numero}"
            : "{$this->repuesto->nombre} llegó al stock mínimo";
    }

    public function mensaje(): string
    {
        if ($this->evento === 'faltante') {
            $lista = ($this->faltantes ?? collect())
                ->map(fn ($r) => InventarioService::cantidad((float) $r->faltante)." {$r->repuesto->unidad} de {$r->repuesto->nombre}")
                ->implode('; ');

            return "Al finalizar la orden {$this->orden?->numero} no alcanzó el stock: faltan {$lista}. El faltante quedó registrado para reposición.";
        }

        return sprintf(
            'El repuesto %s (%s) tiene %s %s; el mínimo es %s. Programe la reposición.',
            $this->repuesto->nombre,
            $this->repuesto->codigo,
            InventarioService::cantidad((float) $this->repuesto->stock),
            $this->repuesto->unidad,
            InventarioService::cantidad((float) $this->repuesto->stock_minimo),
        );
    }

    public function enlace(): ?string
    {
        return $this->evento === 'faltante' && $this->orden ? "/ordenes/{$this->orden->id}" : "/inventario/{$this->repuesto->id}";
    }
}
