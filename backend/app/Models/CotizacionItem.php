<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Renglón de una cotización: descripción, cantidad y valor unitario. */
class CotizacionItem extends Model
{
    protected $table = 'cotizacion_items';

    public $timestamps = false;

    protected $fillable = ['cotizacion_id', 'descripcion', 'cantidad', 'valor_unitario', 'valor_total'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'valor_unitario' => 'decimal:2', 'valor_total' => 'decimal:2'];
    }
}
