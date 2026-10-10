<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('document_type')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'document_type']);
            $table->index(['equipment_id', 'document_type']);
            $table->index('status');
        });

        Schema::create('ai_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['ai_document_id', 'chunk_index']);
        });

        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_document_chunk_id')->constrained()->cascadeOnDelete();
            $table->string('model_name');
            $table->string('model_version')->nullable();
            $table->unsignedInteger('dimensions')->nullable();
            $table->json('vector');
            $table->timestamps();

            $table->unique(['ai_document_chunk_id', 'model_name', 'model_version']);
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['client_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->json('sources')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['ai_conversation_id', 'created_at']);
        });

        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prediction_type');
            $table->string('model_name');
            $table->string('model_version');
            $table->timestampTz('predicted_at');
            $table->timestampTz('horizon_at')->nullable();
            $table->decimal('confidence', 6, 5)->nullable();
            $table->json('input_data')->nullable();
            $table->json('result');
            $table->timestamps();

            $table->index(['equipment_id', 'prediction_type', 'predicted_at']);
            $table->index(['prediction_type', 'predicted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_embeddings');
        Schema::dropIfExists('ai_document_chunks');
        Schema::dropIfExists('ai_documents');
    }
};
