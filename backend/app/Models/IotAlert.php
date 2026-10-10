<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotAlert extends DomainRecord
{
    protected function casts(): array
    {
        return [
            'rule' => 'array',
            'details' => 'array',
            'triggered_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
