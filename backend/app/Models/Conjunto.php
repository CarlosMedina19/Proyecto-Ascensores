<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conjunto, institución o residencia de un cliente. Sus edificios heredan el tipo y, al crearse,
 * la dirección y el contacto; los ascensores heredan la ubicación del edificio.
 */
class Conjunto extends Model
{
    use SoftDeletes, TieneUuid;

    protected $table = 'conjuntos';

    protected $fillable = [
        'cliente_id', 'tipo', 'nombre', 'direccion', 'ciudad', 'departamento', 'codigo_postal',
        'nombre_contacto', 'telefono_contacto', 'correo_contacto', 'observaciones', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class, 'conjunto_id');
    }

    public function equipos(): HasManyThrough
    {
        return $this->hasManyThrough(Equipo::class, Edificio::class, 'conjunto_id', 'edificio_id');
    }
}
