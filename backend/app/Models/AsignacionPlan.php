<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RF-013 y RF-014: plan asignado a un ascensor, con su vigencia, periodicidad,
 * precio pactado y configuración tributaria.
 */
class AsignacionPlan extends Model
{
    use TieneUuid;

    protected $table = 'asignaciones_plan';

    protected $fillable = [
        'equipo_id', 'plan_id', 'periodicidad_id', 'contrato_id', 'precio', 'aplica_iva', 'porcentaje_iva',
        'fecha_inicio', 'fecha_fin', 'estado', 'ultimo_mantenimiento', 'proximo_mantenimiento', 'creado_por', 'hora_preferida',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'porcentaje_iva' => 'decimal:2',
            'aplica_iva' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'ultimo_mantenimiento' => 'date',
            'proximo_mantenimiento' => 'date',
        ];
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'activa');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id')->withTrashed();
    }

    public function periodicidad(): BelongsTo
    {
        return $this->belongsTo(Periodicidad::class, 'periodicidad_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(MantenimientoProgramado::class, 'asignacion_id');
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioAsignacion::class, 'asignacion_id');
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(HistorialPrecio::class, 'asignacion_id');
    }

    /** Valor de cada visita con IVA. */
    public function precioConIva(): string
    {
        $iva = $this->aplica_iva ? (float) $this->porcentaje_iva : 0;

        return number_format((float) $this->precio * (1 + $iva / 100), 2, '.', '');
    }
}
