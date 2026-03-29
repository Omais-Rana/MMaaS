<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('users', 'id')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path'); // storage/pdfFiles/filename.pdf
            $table->string('original_filename');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('file_size'); // In bytes
            $table->integer('total_chunks')->default(0); // Number of chunks after processing
            $table->boolean('is_processed')->default(false); // Whether chunks are in vector_documents
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable(); // If processing failed
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('is_processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
