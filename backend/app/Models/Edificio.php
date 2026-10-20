<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Edificio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'edificios';

    /** Agrupación de los edificios de cada cliente. */
    public const TIPOS = [
        'conjunto' => 'Conjunto',
        'institucional' => 'Institucional',
        'residencial' => 'Residencial',
    ];

    protected $fillable = [
        'identificador_uuid',
        'cliente_id',
        'tipo',
        'nombre',
        'direccion',
        'ciudad',
        'departamento',
        'codigo_postal',
        'pisos',
        'observaciones',
        'nombre_contacto',
        'telefono_contacto',
        'correo_contacto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'pisos' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($modelo) {
            if (empty($modelo->identificador_uuid)) {
                $modelo->identificador_uuid = (string) Str::uuid();
            }
        });
    }

    /** Ubicación que heredan los ascensores del edificio. */
    public function ubicacionEquipos(): string
    {
        return mb_substr("{$this->nombre} · {$this->direccion}, {$this->ciudad}", 0, 255);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'edificio_id');
    }
}
