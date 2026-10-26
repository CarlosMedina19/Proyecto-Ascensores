<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ascensor extends Model
{
    use HasFactory;

    protected $table = 'ascensores';

    /** Tipo según el uso del ascensor. */
    public const TIPOS = [
        'pasajeros' => 'Pasajeros',
        'carga' => 'Carga (montacargas)',
        'camillero' => 'Camillero (hospitalario)',
        'panoramico' => 'Panorámico',
        'vehicular' => 'Vehicular',
        'plataforma' => 'Plataforma para movilidad reducida',
        'montaplatos' => 'Montaplatos',
    ];

    protected $fillable = [
        'equipo_id',
        'tipo_ascensor',
        'marca',
        'modelo',
        'capacidad_kg',
        'capacidad_personas',
        'velocidad_mpm',
        'paradas',
        'tipo_traccion',
        'motor',
        'controlador',
        'tipo_puerta',
        'especificaciones_tecnicas',
        'fecha_instalacion',
        'puertas_cabina',
        'puertas_piso',
        'operador_puertas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
            'capacidad_kg' => 'integer',
            'velocidad_mpm' => 'decimal:2',
            'paradas' => 'integer',
            'puertas_cabina' => 'integer',
            'puertas_piso' => 'integer',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }
}
