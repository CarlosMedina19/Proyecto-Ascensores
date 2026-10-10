<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPrediction extends DomainRecord
{
    protected function casts(): array
    {
        return [
            'predicted_at' => 'immutable_datetime',
            'horizon_at' => 'immutable_datetime',
            'input_data' => 'array',
            'result' => 'array',
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
