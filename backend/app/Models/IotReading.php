<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotReading extends DomainRecord
{
    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IotSensor::class, 'iot_sensor_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
