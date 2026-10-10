<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IotDevice extends DomainRecord
{
    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_seen_at' => 'immutable_datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(self::class, 'gateway_id');
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(IotSensor::class);
    }
}
