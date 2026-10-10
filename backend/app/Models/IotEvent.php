<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotEvent extends DomainRecord
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'data' => 'array'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IotDevice::class, 'iot_device_id');
    }
}
