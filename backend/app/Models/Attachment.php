<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends DomainRecord
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
