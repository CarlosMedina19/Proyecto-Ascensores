<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Factura de un cliente; el saldo baja con cada pago registrado. */
class Factura extends Model
{
    protected $table = 'facturas';

    public const ESTADOS = ['pendiente' => 'Pendiente', 'parcial' => 'Abono', 'pagada' => 'Pagada'];

    protected $fillable = ['cliente_id', 'numero', 'concepto', 'fecha_emision', 'fecha_vencimiento', 'valor', 'saldo', 'estado', 'creado_por'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'valor' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    /** Días de mora: desde el vencimiento, solo si aún hay saldo. */
    public function diasMora(): int
    {
        if ((float) $this->saldo <= 0 || $this->fecha_vencimiento->isFuture()) {
            return 0;
        }

        return (int) $this->fecha_vencimiento->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'factura_id')->orderBy('fecha');
    }
}
