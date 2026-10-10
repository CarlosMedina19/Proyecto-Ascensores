<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends DomainRecord
{
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
