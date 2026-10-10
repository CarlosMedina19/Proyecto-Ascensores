<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEquipment extends DomainRecord
{
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
