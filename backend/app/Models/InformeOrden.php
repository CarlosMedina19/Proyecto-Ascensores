<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-039: informe PDF emitido. No se modifica; las correcciones generan otra versión.
 */
class InformeOrden extends Model
{
    use Inmutable;

    public $timestamps = false;

    protected $table = 'informes_orden';

    protected $fillable = ['orden_id', 'version', 'ruta', 'hash', 'tamano', 'motivo', 'generado_por'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'version' => 'integer', 'tamano' => 'integer'];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'generado_por');
    }
}
