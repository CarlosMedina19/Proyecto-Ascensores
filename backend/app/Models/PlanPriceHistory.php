<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPriceHistory extends DomainRecord
{
    public $timestamps = false;

    protected $table = 'plan_price_history';

    protected function casts(): array
    {
        return ['changed_at' => 'immutable_datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PlanAssignment::class, 'plan_assignment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
