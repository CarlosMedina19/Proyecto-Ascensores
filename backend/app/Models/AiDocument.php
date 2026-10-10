<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocument extends DomainRecord
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiDocumentChunk::class);
    }
}
