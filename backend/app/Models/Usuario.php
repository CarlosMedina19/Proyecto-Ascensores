<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'rol_id',
        'cliente_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'correo_verificado_en' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function tieneRol(string $nombreRol): bool
    {
        return $this->rol?->nombre === $nombreRol;
    }

    public function tienePermiso(string $nombrePermiso): bool
    {
        return $this->rol?->tienePermiso($nombrePermiso) ?? false;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }
}
