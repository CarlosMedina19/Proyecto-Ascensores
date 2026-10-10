<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Technician extends DomainRecord
{
    protected function casts(): array
    {
        return ['availability' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class, 'work_order_technicians')
            ->withPivot(['assigned_by', 'assigned_at', 'accepted_at'])
            ->withTimestamps();
    }
}
