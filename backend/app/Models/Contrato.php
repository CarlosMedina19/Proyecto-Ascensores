<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RF-019: contrato con cliente, equipos, servicios, vigencia, valor, condiciones y estado.
 */
class Contrato extends Model
{
    use TieneUuid;

    protected $table = 'contratos';

    protected $fillable = [
        'numero', 'cliente_id', 'plan_id', 'periodicidad_id', 'precio_periodo', 'servicios_pactados',
        'contrato_anterior_id', 'fecha_inicio', 'fecha_fin', 'valor', 'servicios',
        'condiciones', 'estado', 'estado_renovacion', 'alerta_vencimiento_en', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'valor' => 'decimal:2',
            'precio_periodo' => 'decimal:2',
            'servicios_pactados' => 'array',
            'alerta_vencimiento_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** Plan de alquiler del software que se pactó (puede ajustarse en el contrato). */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id')->withTrashed();
    }

    public function periodicidad(): BelongsTo
    {
        return $this->belongsTo(Periodicidad::class, 'periodicidad_id');
    }

    /** Periodos de facturación que caben en la vigencia (un trimestre empezado cuenta completo). */
    public function periodos(): ?int
    {
        if (! $this->periodicidad || ! $this->fecha_inicio || ! $this->fecha_fin) {
            return null;
        }
        $meses = $this->fecha_inicio->diffInMonths($this->fecha_fin->copy()->addDay());

        return max(1, (int) ceil(round($meses, 2) / $this->periodicidad->meses));
    }

    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'contrato_equipos', 'contrato_id', 'equipo_id')->withTimestamps();
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoRenovacion::class, 'contrato_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPlan::class, 'contrato_id');
    }

    public function contratoAnterior(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_anterior_id');
    }

    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_fin->copy()->startOfDay(), false);
    }
}
