<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Equipo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipos';

    protected $fillable = [
        'identificador_uuid',
        'edificio_id',
        'codigo',
        'tipo',
        'marca',
        'modelo',
        'numero_serie',
        'ubicacion',
        'estado',
        'fecha_instalacion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
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

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    public function ascensor(): HasOne
    {
        return $this->hasOne(Ascensor::class, 'equipo_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEquipo::class, 'equipo_id');
    }
}
