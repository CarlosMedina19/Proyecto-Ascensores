<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RF-037: resultado (INS, EST o NA) y estado (B, R o M) de un ítem de la planilla.
 */
class PlanillaResultado extends Model
{
    protected $table = 'planilla_resultados';

    public const RESULTADOS = ['INS', 'EST', 'NA'];

    public const ESTADOS = ['B', 'R', 'M'];

    protected $fillable = ['planilla_id', 'item_id', 'resultado', 'estado', 'observacion', 'notificado_en', 'actualizado_por'];

    protected function casts(): array
    {
        return ['notificado_en' => 'datetime'];
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'item_id');
    }

    /** RF-038: órdenes correctivas creadas desde este ítem. */
    public function correctivos(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class, 'resultado_origen_id');
    }

    public function correctivoAbierto(): HasOne
    {
        return $this->hasOne(OrdenTrabajo::class, 'resultado_origen_id')->whereNotIn('estado', OrdenTrabajo::FINALES);
    }
}
