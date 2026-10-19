<?php

namespace App\Models;

use App\Models\Concerns\Inmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-020: seguimiento de la renovación (contactado, renovado o no renovado).
 */
class SeguimientoRenovacion extends Model
{
    use Inmutable;

    protected $table = 'seguimientos_renovacion';

    public $timestamps = false;

    protected $fillable = ['contrato_id', 'usuario_id', 'resultado', 'observacion'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
