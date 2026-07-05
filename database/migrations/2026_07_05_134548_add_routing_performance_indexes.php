<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes that target the exact query patterns used
     * by the RouteSearchController routing engine.
     */
    public function up(): void
    {
        Schema::table('stop_times', function (Blueprint $table) {
            $table->index(['stop_id', 'trip_id', 'departure_time'], 'idx_st_stop_trip_dept');
        });

        Schema::table('shapes', function (Blueprint $table) {
            $table->index(['shape_id', 'shape_pt_sequence'], 'idx_shapes_id_seq');
        });
    }

    public function down(): void
    {
        Schema::table('stop_times', function (Blueprint $table) {
            $table->dropIndex('idx_st_stop_trip_dept');
        });

        Schema::table('shapes', function (Blueprint $table) {
            $table->dropIndex('idx_shapes_id_seq');
        });
    }
};
