<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vector_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('users', 'id')->onDelete('cascade');
            $table->enum('source_type', ['route', 'stop', 'pdf']); // Data source type
            $table->unsignedBigInteger('source_id')->nullable(); // FK to routes.id, stops.id, or pdf_documents.id
            $table->text('content_chunk'); // Text content to embed
            $table->string('content_hash')->unique(); // Hash for deduplication
            $table->json('embedding')->nullable(); // Vector embedding (array)
            $table->string('embedding_model')->default('all-MiniLM-L6-v2'); // Model used
            $table->text('metadata')->nullable(); // JSON: route_name, stop_name, pdf_title, etc
            $table->integer('chunk_index')->nullable(); // Order in multi-chunk document
            $table->decimal('similarity_threshold', 3, 2)->default(0.7); // For retrieval
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('source_type');
            $table->index('source_id');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_documents');
    }
};
