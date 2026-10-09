<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEquipo extends Model
{
    protected $table = 'historial_equipos';

    public $timestamps = false;

    protected $fillable = ['equipo_id', 'usuario_id', 'evento', 'descripcion'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
