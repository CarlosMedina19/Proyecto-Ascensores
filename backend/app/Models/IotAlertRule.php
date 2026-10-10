<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotAlertRule extends DomainRecord
{
    protected function casts(): array
    {
        return ['condition' => 'array', 'is_active' => 'boolean'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IotSensor::class, 'iot_sensor_id');
    }
}
