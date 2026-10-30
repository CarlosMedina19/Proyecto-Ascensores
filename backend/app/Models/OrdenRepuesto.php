<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RF-042: repuesto usado en una orden; se descuenta del inventario al finalizarla. */
class OrdenRepuesto extends Model
{
    protected $table = 'orden_repuestos';

    protected $fillable = ['orden_id', 'repuesto_id', 'cantidad', 'cantidad_descontada', 'faltante', 'registrado_por'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'cantidad_descontada' => 'decimal:2',
            'faltante' => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }
}
