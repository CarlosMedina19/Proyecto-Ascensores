<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends DomainRecord
{
    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class);
    }
}
