<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * RF-022: especialidad de los técnicos (catálogo configurable).
 */
class Especialidad extends Model
{
    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tecnicos(): BelongsToMany
    {
        return $this->belongsToMany(Tecnico::class, 'especialidad_tecnico');
    }
}
