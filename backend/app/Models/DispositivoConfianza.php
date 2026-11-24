<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Navegador o celular donde el usuario ya verificó el código de dos pasos. */
class DispositivoConfianza extends Model
{
    protected $table = 'dispositivos_confianza';

    protected $guarded = [];

    protected $hidden = ['huella'];

    protected function casts(): array
    {
        return ['ultimo_uso_en' => 'datetime', 'expira_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
