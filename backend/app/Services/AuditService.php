<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public static function log(string $action, Model $model, ?array $changes = null): AuditLog
    {
        return AuditLog::create([
            'usuario_id' => Auth::id(),
            'accion' => $action,
            'modelo' => get_class($model),
            'modelo_id' => $model->getKey(),
            'cambios' => $changes ?? $model->getChanges(),
        ]);
    }
}
