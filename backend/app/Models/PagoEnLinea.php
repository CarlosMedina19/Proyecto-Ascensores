<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Intento de pago de una factura por PSE en la pasarela Wompi. */
class PagoEnLinea extends Model
{
    protected $table = 'pagos_en_linea';

    protected $fillable = ['factura_id', 'referencia', 'monto_centavos', 'estado', 'transaccion_id', 'medio', 'pago_id', 'usuario_id', 'respuesta'];

    protected function casts(): array
    {
        return ['respuesta' => 'array', 'monto_centavos' => 'integer'];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }
}
