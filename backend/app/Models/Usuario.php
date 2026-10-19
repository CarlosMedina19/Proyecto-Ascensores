<?php

namespace App\Models;

use App\Notifications\RestablecerContrasenaNotification;
use App\Notifications\VerificarCorreoNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable implements CanResetPassword
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
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
            'intentos_fallidos' => 'integer',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * El correo se guarda en minúsculas para que el inicio de sesión no dependa de mayúsculas.
     */
    protected function correo(): Attribute
    {
        return Attribute::make(set: fn (string $valor) => Str::lower(trim($valor)));
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** RF-022: perfil de técnico (solo los usuarios de rol técnico lo tienen). */
    public function tecnico(): HasOne
    {
        return $this->hasOne(Tecnico::class, 'usuario_id');
    }

    public function esTecnico(): bool
    {
        return $this->tieneRol('tecnico');
    }

    public function tieneRol(string $nombreRol): bool
    {
        return $this->rol?->nombre === $nombreRol;
    }

    public function esCliente(): bool
    {
        return $this->tieneRol('cliente');
    }

    public function tienePermiso(string $nombrePermiso): bool
    {
        return $this->rol?->tienePermiso($nombrePermiso) ?? false;
    }

    /**
     * Un usuario con rol cliente solo accede a los datos de su propio cliente (RNF-02).
     */
    public function puedeVerCliente(?int $clienteId): bool
    {
        return ! $this->esCliente() || ($clienteId !== null && $this->cliente_id === $clienteId);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasenaNotification($token));
    }

    public function enviarVerificacionCorreo(): void
    {
        $this->notify(new VerificarCorreoNotification);
    }
}
