<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cotización de un mantenimiento correctivo que el cliente aprueba o rechaza desde su portal. */
class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    public const ESTADOS = ['enviada' => 'Esperando respuesta', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'vencida' => 'Vencida'];

    protected $fillable = [
        'numero', 'equipo_id', 'cliente_id', 'orden_id', 'factura_id', 'resultado_origen_id', 'titulo', 'descuento', 'descripcion', 'subtotal', 'iva', 'total',
        'fecha_emision', 'vigente_hasta', 'estado', 'respondida_en', 'respondida_por', 'motivo_rechazo', 'creada_por',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_emision' => 'date',
            'vigente_hasta' => 'date',
            'respondida_en' => 'datetime',
        ];
    }

    /** Estado efectivo: una cotización enviada cuya vigencia pasó se muestra como vencida. */
    public function estadoActual(): string
    {
        return $this->estado === 'enviada' && $this->vigente_hasta->lt(now()->startOfDay()) ? 'vencida' : $this->estado;
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    /** Ítem de la planilla (en R o M) que originó la cotización. */
    public function resultadoOrigen(): BelongsTo
    {
        return $this->belongsTo(PlanillaResultado::class, 'resultado_origen_id');
    }

    /** RF-045: factura generada desde la cotización aprobada. */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class, 'cotizacion_id')->orderBy('id');
    }

    public function respondidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'respondida_por')->withTrashed();
    }
}
