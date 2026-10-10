<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends DomainRecord
{
    public function workOrderParts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
