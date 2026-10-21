<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-029: cada cambio o acción sobre una orden (usuario, fecha, valor anterior y nuevo).
 * No se edita ni se elimina.
 */
class TrazabilidadOrden extends Model
{
    use Inmutable;

    public $timestamps = false;

    protected $table = 'trazabilidad_ordenes';

    protected $fillable = ['orden_id', 'usuario_id', 'accion', 'campo', 'valor_anterior', 'valor_nuevo', 'detalle'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }
}
