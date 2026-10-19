<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-021: registro de cada aviso, con su fecha y estado de envío.
 * El índice único impide enviar dos veces el mismo aviso por el mismo canal.
 */
class EnvioNotificacion extends Model
{
    protected $table = 'envios_notificacion';

    public $timestamps = false;

    protected $fillable = ['tipo', 'referencia_tipo', 'referencia_id', 'usuario_id', 'canal', 'estado', 'error', 'enviado_en'];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (EnvioNotificacion $envio) => $envio->created_at ??= now());
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
