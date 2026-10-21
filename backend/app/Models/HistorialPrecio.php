<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-015: precio anterior, precio nuevo, fecha, usuario y motivo de cada cambio.
 * Los precios anteriores nunca se eliminan.
 */
class HistorialPrecio extends Model
{
    use Inmutable;

    protected $table = 'historial_precios';

    public $timestamps = false;

    protected $fillable = ['plan_id', 'periodicidad_id', 'asignacion_id', 'precio_anterior', 'precio_nuevo', 'motivo', 'usuario_id'];

    protected function casts(): array
    {
        return ['precio_anterior' => 'decimal:2', 'precio_nuevo' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }

    public function periodicidad(): BelongsTo
    {
        return $this->belongsTo(Periodicidad::class, 'periodicidad_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id')->withTrashed();
    }
}
