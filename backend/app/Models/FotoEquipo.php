<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto del ascensor guardada en el disco privado. Se descarga con el token del usuario.
 */
class FotoEquipo extends Model
{
    public const MAXIMO_POR_EQUIPO = 3;

    protected $table = 'fotos_equipo';

    protected $fillable = ['equipo_id', 'ruta', 'nombre_original', 'tipo_mime', 'tamano', 'orden', 'usuario_id'];

    protected function casts(): array
    {
        return ['tamano' => 'integer', 'orden' => 'integer'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
