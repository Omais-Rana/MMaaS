<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('conversation_log_id')->nullable()->constrained('conversation_logs')->onDelete('set null');
            $table->boolean('is_helpful')->nullable(); // thumbs up/down
            $table->string('feedback_reason')->nullable(); // accurate, inaccurate, slow, confusing, incomplete
            $table->text('feedback_text')->nullable(); // User's comment
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('is_helpful');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
