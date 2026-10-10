<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAssignment extends DomainRecord
{
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PlanPriceHistory::class);
    }
}
