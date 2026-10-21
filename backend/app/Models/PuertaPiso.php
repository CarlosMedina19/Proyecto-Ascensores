<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Puerta de piso de un ascensor: una por parada, con su tipo y el último estado revisado. */
class PuertaPiso extends Model
{
    protected $table = 'puertas_piso';

    public const TIPOS = ['automatica' => 'Automática', 'manual' => 'Manual'];

    public const ESTADOS = ['B' => 'Bueno', 'R' => 'Regular', 'M' => 'Malo', 'P' => 'Pendiente'];

    protected $fillable = ['equipo_id', 'piso', 'tipo', 'estado', 'observacion'];

    protected function casts(): array
    {
        return ['piso' => 'integer'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }
}
