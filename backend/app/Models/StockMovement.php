<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends DomainRecord
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
