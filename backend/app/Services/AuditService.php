<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Registrar una acción en la bitácora de auditoría (RF-004).
     */
    public static function log(string $action, Model $model, ?array $changes = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'changes' => $changes ?? $model->getChanges(),
        ]);
    }
}
