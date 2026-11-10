<?php

namespace App\Models;

use App\Events\RegistroAuditoriaCreado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * RF-004: los registros de auditoría no pueden editarse ni eliminarse.
 */
class RegistroAuditoria extends Model
{
    protected $table = 'registros_auditoria';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'accion', 'modulo', 'modelo', 'modelo_id', 'cambios', 'ip'];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RegistroAuditoria $registro) {
            $registro->created_at ??= now();
        });
        // Auditoría en vivo: cada registro nuevo se anuncia en el canal privado «auditoria».
        static::created(function (RegistroAuditoria $registro) {
            try {
                RegistroAuditoriaCreado::dispatch($registro);
            } catch (Throwable $e) {
                Log::warning('Tiempo real: no se pudo anunciar el registro de auditoría', ['error' => $e->getMessage()]);
            }
        });

        static::updating(fn () => throw new LogicException('Los registros de auditoría no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se pueden eliminar.'));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
