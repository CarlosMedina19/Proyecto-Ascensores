<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** RF-040: repuesto del inventario con su stock y stock mínimo. */
class Repuesto extends Model
{
    protected $table = 'repuestos';

    public const UNIDADES = ['unidad' => 'Unidad', 'metro' => 'Metro', 'litro' => 'Litro', 'kilogramo' => 'Kilogramo', 'juego' => 'Juego', 'caja' => 'Caja'];

    protected $fillable = [
        'codigo', 'nombre', 'marca', 'referencia', 'unidad', 'precio', 'stock', 'stock_minimo', 'activo', 'alerta_stock_en', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'activo' => 'boolean',
            'alerta_stock_en' => 'datetime',
        ];
    }

    /** RF-043: el stock está en el mínimo o por debajo (solo si se definió un mínimo). */
    public function bajoMinimo(): bool
    {
        return (float) $this->stock_minimo > 0 && (float) $this->stock <= (float) $this->stock_minimo;
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'repuesto_id');
    }
}
