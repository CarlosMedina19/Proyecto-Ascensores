<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-025 y RF-039: firma del técnico o de quien recibe el servicio (imagen PNG, disco privado).
 */
class FirmaOrden extends Model
{
    protected $table = 'firmas_orden';

    public const TIPOS = ['tecnico' => 'Técnico', 'cliente' => 'Recibe por el cliente'];

    protected $fillable = ['orden_id', 'tipo', 'nombre', 'documento', 'cargo', 'ruta', 'registrada_por', 'firmado_en'];

    protected function casts(): array
    {
        return ['firmado_en' => 'datetime'];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }
}
