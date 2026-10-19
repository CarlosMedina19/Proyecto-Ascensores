<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RF-018: mantenimiento futuro generado a partir de la periodicidad del plan.
 * `fecha_teorica` es la fecha calculada; `fecha_programada`, la del siguiente día hábil
 * o la que fijó el coordinador al reprogramar.
 */
class MantenimientoProgramado extends Model
{
    use TieneUuid;

    protected $table = 'mantenimientos_programados';

    public const PENDIENTES = ['programado', 'reprogramado'];

    protected $fillable = [
        'asignacion_id', 'equipo_id', 'fecha_programada', 'fecha_teorica', 'precio', 'estado',
        'fecha_ejecucion', 'motivo', 'observacion', 'actualizado_por', 'hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_teorica' => 'date',
            'fecha_ejecucion' => 'date',
            'precio' => 'decimal:2',
        ];
    }

    public function scopePendientes(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', self::PENDIENTES);
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(AsignacionPlan::class, 'asignacion_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /** Fase 3: orden de trabajo vigente (ni finalizada ni cancelada) creada para esta visita. */
    public function ordenActiva(): HasOne
    {
        return $this->hasOne(OrdenTrabajo::class, 'mantenimiento_id')->whereNotIn('estado', OrdenTrabajo::FINALES);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class, 'mantenimiento_id');
    }

    public function estaPendiente(): bool
    {
        return in_array($this->estado, self::PENDIENTES, true);
    }
}
