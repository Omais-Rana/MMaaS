<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('favorite_route_id')->nullable();
            $table->string('favorite_origin_name')->nullable();
            $table->string('favorite_destination_name')->nullable();
            $table->json('preferred_times')->nullable(); // {morning: 8:30, evening: 18:00}
            $table->boolean('accessibility_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index('favorite_route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
