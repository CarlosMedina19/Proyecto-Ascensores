<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IotSensor extends DomainRecord
{
    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'iot_device_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(IotReading::class);
    }
}
