<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RF-011: periodicidad del mantenimiento en meses, configurable sin modificar el código.
 */
class Periodicidad extends Model
{
    protected $table = 'periodicidades';

    protected $fillable = ['meses', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['meses' => 'integer', 'activo' => 'boolean'];
    }
}
