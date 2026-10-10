<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Signature extends DomainRecord
{
    protected function casts(): array
    {
        return ['signed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }
}
