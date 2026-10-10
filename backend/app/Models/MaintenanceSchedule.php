<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSchedule extends DomainRecord
{
    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PlanAssignment::class, 'plan_assignment_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
