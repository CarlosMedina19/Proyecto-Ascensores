<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentHistory extends Model
{
    protected $table = 'historial_equipos';

    public $timestamps = false;

    protected $fillable = ['equipo_id', 'usuario_id', 'evento', 'descripcion'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
