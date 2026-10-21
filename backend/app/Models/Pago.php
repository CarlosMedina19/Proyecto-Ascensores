<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago recibido de un cliente y aplicado a una factura. */
class Pago extends Model
{
    protected $table = 'pagos';

    public const MEDIOS = ['transferencia' => 'Transferencia', 'consignacion' => 'Consignación', 'cheque' => 'Cheque', 'efectivo' => 'Efectivo'];

    protected $fillable = ['cliente_id', 'factura_id', 'valor', 'fecha', 'medio', 'referencia', 'registrado_por'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'valor' => 'decimal:2'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
