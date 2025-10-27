<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing null values to 0
        DB::table('trips')->whereNull('wheelchair_accessible')->update(['wheelchair_accessible' => 0]);
        DB::table('stops')->whereNull('wheelchair_boarding')->update(['wheelchair_boarding' => 0]);

        // Alter columns to have default value and not nullable
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('wheelchair_accessible')->default(0)->nullable(false)->change();
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->integer('wheelchair_boarding')->default(0)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('wheelchair_accessible')->nullable()->change();
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->integer('wheelchair_boarding')->nullable()->change();
        });
    }
};
