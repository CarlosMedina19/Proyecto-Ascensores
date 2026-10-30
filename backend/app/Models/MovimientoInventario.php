<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RF-041: movimiento de inventario. No se edita; los errores se corrigen con un ajuste. */
class MovimientoInventario extends Model
{
    use Inmutable;

    protected $table = 'movimientos_inventario';

    public const UPDATED_AT = null;

    public const TIPOS = ['entrada' => 'Entrada', 'salida' => 'Salida', 'ajuste' => 'Ajuste', 'devolucion' => 'Devolución', 'consumo' => 'Consumo en orden'];

    protected $fillable = ['repuesto_id', 'tipo', 'cantidad', 'variacion', 'stock_resultante', 'motivo', 'orden_id', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'variacion' => 'decimal:2',
            'stock_resultante' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
