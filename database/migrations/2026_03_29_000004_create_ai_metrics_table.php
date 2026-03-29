<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->foreignId('conversation_log_id')->nullable()->constrained('conversation_logs')->onDelete('set null');
            $table->string('agent_type'); // conversational, recommendation
            $table->string('intent')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('estimated_cost', 8, 6)->nullable(); // USD
            $table->integer('latency_ms')->nullable();
            $table->string('model_version')->default('mistral-large-latest');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index('agent_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_metrics');
    }
};
