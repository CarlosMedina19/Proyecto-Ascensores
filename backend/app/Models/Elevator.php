<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Elevator extends Model
{
    use HasFactory;

    protected $table = 'ascensores';

    protected $fillable = [
        'equipo_id',
        'marca',
        'modelo',
        'capacidad_kg',
        'velocidad_mpm',
        'paradas',
        'tipo_traccion',
        'motor',
        'controlador',
        'tipo_puerta',
        'especificaciones_tecnicas',
        'fecha_instalacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
            'capacidad_kg' => 'integer',
            'velocidad_mpm' => 'decimal:2',
            'paradas' => 'integer',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipo_id');
    }
}
