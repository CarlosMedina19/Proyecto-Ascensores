<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'identificador_uuid',
        'codigo',
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

        static::creating(function ($modelo) {
            if (empty($modelo->identificador_uuid)) {
                $modelo->identificador_uuid = (string) Str::uuid();
            }
        });

        // Código para relacionar las hojas de Excel: CL-0001, CL-0002… si no se indicó uno.
        static::created(function ($modelo) {
            if (empty($modelo->codigo)) {
                $modelo->forceFill(['codigo' => sprintf('CL-%04d', $modelo->id)])->saveQuietly();
            }
        });
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoCliente::class, 'cliente_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'cliente_id');
    }

    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class, 'cliente_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'cliente_id');
    }
}
