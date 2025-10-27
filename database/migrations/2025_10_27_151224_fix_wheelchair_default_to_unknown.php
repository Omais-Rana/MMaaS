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
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('wheelchair_accessible')->default(2)->change();
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->integer('wheelchair_boarding')->default(2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('wheelchair_accessible')->default(0)->change();
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->integer('wheelchair_boarding')->default(0)->change();
        });
    }
};
