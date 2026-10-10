<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends DomainRecord
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'work_order_technicians')
            ->withPivot(['assigned_by', 'assigned_at', 'accepted_at'])
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class);
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(WorkOrderChecklist::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }
}
