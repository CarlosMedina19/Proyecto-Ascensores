<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends DatabaseModel
{
    public $timestamps = false; // solo tenemos created_at

    protected $fillable = ['user_id', 'ip_address', 'action', 'model', 'model_id', 'changes'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
