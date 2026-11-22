<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de un solo uso para el segundo paso del inicio de sesión o la recuperación por teléfono. */
class CodigoVerificacion extends Model
{
    public const INICIO_SESION = 'inicio_sesion';

    public const RECUPERACION = 'recuperacion';

    protected $table = 'codigos_verificacion';

    protected $guarded = [];

    protected $hidden = ['codigo', 'desafio'];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
            'reenviado_en' => 'datetime',
            'intentos' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->whereNull('usado_en')->where('expira_en', '>', now());
    }
}
