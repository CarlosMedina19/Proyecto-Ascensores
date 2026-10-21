<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * RF-022: perfil del técnico. Su estado (activo/inactivo) es el de su cuenta de usuario.
 */
class Tecnico extends Model
{
    protected $table = 'tecnicos';

    protected $fillable = [
        'usuario_id', 'tipo_documento', 'numero_documento', 'telefono', 'disponibilidad',
        'motivo_no_disponible', 'no_disponible_hasta', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['no_disponible_hasta' => 'date'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'especialidad_tecnico')->orderBy('nombre');
    }

    public function ordenes(): BelongsToMany
    {
        return $this->belongsToMany(OrdenTrabajo::class, 'orden_tecnico', 'tecnico_id', 'orden_id')
            ->withPivot(['asignado_por', 'asignado_en']);
    }

    /** RF-022: solo los técnicos activos y disponibles reciben órdenes. */
    public function scopeAsignables(Builder $consulta): Builder
    {
        return $consulta->where('disponibilidad', 'disponible')
            ->whereHas('usuario', fn ($u) => $u->where('activo', true));
    }

    public function puedeRecibirOrdenes(): bool
    {
        return $this->disponibilidad === 'disponible' && (bool) $this->usuario?->activo;
    }
}
