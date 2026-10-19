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
        'numero', 'cliente_id', 'contrato_anterior_id', 'fecha_inicio', 'fecha_fin', 'valor', 'servicios',
        'condiciones', 'estado', 'estado_renovacion', 'alerta_vencimiento_en', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'valor' => 'decimal:2',
            'alerta_vencimiento_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
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
