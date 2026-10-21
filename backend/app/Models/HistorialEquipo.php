<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * RF-009: los registros históricos no se modifican ni se eliminan;
 * las correcciones se hacen con nuevos registros.
 */
class HistorialEquipo extends Model
{
    protected $table = 'historial_equipos';

    public $timestamps = false;

    protected $fillable = ['equipo_id', 'usuario_id', 'evento', 'descripcion'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (HistorialEquipo $registro) {
            $registro->created_at ??= now();
        });

        static::updating(fn () => throw new LogicException('El historial del equipo no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El historial del equipo no se puede eliminar.'));
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
