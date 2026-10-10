<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends DomainRecord
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'settings' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
