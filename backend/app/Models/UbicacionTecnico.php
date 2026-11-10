<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Posición del técnico mientras su orden está en proceso (mapa de Auditoría). */
class UbicacionTecnico extends Model
{
    protected $table = 'ubicaciones_tecnico';

    public $timestamps = false;

    protected $fillable = ['tecnico_id', 'orden_id', 'latitud', 'longitud', 'precision_metros', 'origen', 'registrada_en'];

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
            'precision_metros' => 'float',
            'registrada_en' => 'datetime',
        ];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    /** Lo que muestra el mapa: técnico, orden, ascensor, edificio y posición. */
    public function resumen(): array
    {
        $this->loadMissing(['tecnico.usuario', 'orden.equipo', 'orden.edificio']);

        return [
            'tecnico_id' => $this->tecnico_id,
            'tecnico' => $this->tecnico?->usuario?->nombre,
            'orden' => $this->orden ? ['id' => $this->orden->id, 'numero' => $this->orden->numero, 'estado' => $this->orden->estado] : null,
            'equipo' => $this->orden?->equipo?->codigo,
            'edificio' => $this->orden?->edificio?->nombre,
            'inicio_en' => $this->orden?->inicio_en?->toIso8601String(),
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'precision_metros' => $this->precision_metros,
            'registrada_en' => $this->registrada_en?->toIso8601String(),
        ];
    }
}
