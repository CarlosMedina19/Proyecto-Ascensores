<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada cambio de estado de una emergencia. */
class SeguimientoEmergencia extends Model
{
    protected $table = 'seguimientos_emergencia';

    public const UPDATED_AT = null;

    protected $fillable = ['emergencia_id', 'estado', 'nota', 'usuario_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
