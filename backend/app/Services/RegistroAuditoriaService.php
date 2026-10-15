<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RegistroAuditoriaService
{
    public static function registrar(string $accion, Model $modelo, ?array $cambios = null): RegistroAuditoria
    {
        return RegistroAuditoria::create([
            'usuario_id' => Auth::id(),
            'accion' => $accion,
            'modelo' => get_class($modelo),
            'modelo_id' => $modelo->getKey(),
            'cambios' => $cambios ?? $modelo->getChanges(),
        ]);
    }
}
