<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Building extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'edificios';

    protected $fillable = [
        'identificador_uuid',
        'cliente_id',
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
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->identificador_uuid)) {
                $model->identificador_uuid = (string) Str::uuid();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'edificio_id');
    }
}
