<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gbfs_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbfs_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('station_id');
            $table->string('name');
            $table->decimal('lat', 10, 8);
            $table->decimal('lon', 11, 8);
            $table->integer('capacity')->nullable();
            $table->timestamps();

            $table->unique(['gbfs_source_id', 'station_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gbfs_stations');
    }
};
