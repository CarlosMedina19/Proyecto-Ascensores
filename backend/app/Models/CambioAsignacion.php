<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-016: cada cambio de plan, periodicidad o estado registra usuario, fecha y motivo.
 */
class CambioAsignacion extends Model
{
    use Inmutable;

    protected $table = 'cambios_asignacion';

    public $timestamps = false;

    protected $fillable = ['asignacion_id', 'usuario_id', 'campo', 'valor_anterior', 'valor_nuevo', 'motivo'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
