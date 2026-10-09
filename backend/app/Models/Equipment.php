<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Equipment extends Model
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

        static::creating(function ($model) {
            if (empty($model->identificador_uuid)) {
                $model->identificador_uuid = (string) Str::uuid();
            }
        });
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'edificio_id');
    }

    public function elevator(): HasOne
    {
        return $this->hasOne(Elevator::class, 'equipo_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EquipmentHistory::class, 'equipo_id');
    }
}
