<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends DomainRecord
{
    public function assignments(): HasMany
    {
        return $this->hasMany(PlanAssignment::class);
    }
}
