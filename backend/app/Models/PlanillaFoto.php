<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fotografía tomada durante la planilla (general o de un ítem). Disco privado.
 */
class PlanillaFoto extends Model
{
    protected $table = 'planilla_fotos';

    public const MAXIMO_POR_PLANILLA = 20;

    protected $fillable = ['planilla_id', 'item_id', 'ruta', 'nombre_original', 'tipo_mime', 'tamano', 'descripcion', 'usuario_id'];

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'item_id');
    }
}
