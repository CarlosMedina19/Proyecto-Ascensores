<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocumentChunk extends DomainRecord
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'ai_document_id');
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(AiEmbedding::class, 'ai_document_chunk_id');
    }
}
