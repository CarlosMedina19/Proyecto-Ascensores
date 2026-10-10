<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderTechnician extends DomainRecord
{
    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
