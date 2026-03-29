<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->string('agent_type')->default('conversational'); // conversational, recommendation
            $table->text('user_message');
            $table->string('intent')->nullable(); // RouteSearch, StopLookup, FAQSearch, Recommendation
            $table->json('intent_data')->nullable(); // Extracted params (origin, dest, date, time, etc)
            $table->text('agent_response');
            $table->json('tools_used')->nullable(); // ['RouteTool', 'FAQTool']
            $table->json('retrieved_documents')->nullable(); // For RAG citations
            $table->decimal('confidence_score', 3, 2)->nullable(); // 0.00 - 1.00
            $table->integer('tokens_used')->nullable(); // For cost tracking
            $table->decimal('estimated_cost', 8, 6)->nullable(); // Mistral API cost
            $table->integer('response_time_ms')->nullable(); // Latency in milliseconds
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index('agent_type');
            $table->index('intent');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_logs');
    }
};
