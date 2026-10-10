<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends DomainRecord
{
    protected $table = 'ai_messages';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['sources' => 'array', 'metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AiFeedback::class, 'ai_message_id');
    }
}
