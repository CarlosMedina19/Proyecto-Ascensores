<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEmbedding extends DomainRecord
{
    protected $table = 'ai_embeddings';

    protected function casts(): array
    {
        return ['vector' => 'array'];
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiDocumentChunk::class, 'ai_document_chunk_id');
    }
}
