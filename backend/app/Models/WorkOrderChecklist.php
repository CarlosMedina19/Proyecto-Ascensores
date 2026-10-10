<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderChecklist extends DomainRecord
{
    protected $table = 'work_order_checklist';

    protected function casts(): array
    {
        return ['corrective_required' => 'boolean', 'completed_at' => 'immutable_datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
