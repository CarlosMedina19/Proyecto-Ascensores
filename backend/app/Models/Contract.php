<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contract extends DomainRecord
{
    protected function casts(): array
    {
        return ['tax_configuration' => 'array', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'contract_equipment')
            ->withPivot(['plan_assignment_id', 'service_description', 'price'])
            ->withTimestamps();
    }
}
