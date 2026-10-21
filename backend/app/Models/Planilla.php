<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RF-030: planilla digital de mantenimiento de una orden preventiva (una por orden).
 */
class Planilla extends Model
{
    protected $table = 'planillas';

    protected $fillable = ['orden_id', 'tecnico_usuario_id', 'recomendaciones', 'iniciada_en', 'completada_en'];

    protected function casts(): array
    {
        return ['iniciada_en' => 'datetime', 'completada_en' => 'datetime'];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(PlanillaResultado::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PlanillaFoto::class)->orderBy('id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_usuario_id')->withTrashed();
    }
}
