<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends DomainRecord
{
    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }
}
