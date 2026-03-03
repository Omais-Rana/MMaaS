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
        Schema::create('shapes', function (Blueprint $table) {
            $table->id();
            $table->string('shape_id')->index();
            $table->decimal('shape_pt_lat', 10, 6);
            $table->decimal('shape_pt_lon', 10, 6);
            $table->integer('shape_pt_sequence');
            $table->decimal('shape_dist_traveled', 10, 4)->nullable();

            $table->index(['shape_id', 'shape_pt_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shapes');
    }
};
