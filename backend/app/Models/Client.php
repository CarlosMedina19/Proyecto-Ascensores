<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'identificador_uuid',
        'tipo',
        'nombre',
        'tipo_documento',
        'numero_documento',
        'nit',
        'direccion',
        'telefono',
        'correo',
        'regimen_tributario',
        'actividad_economica',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
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

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'cliente_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'cliente_id');
    }
}
