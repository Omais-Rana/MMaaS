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
            $table->integer('wheelchair_accessible')->nullable()->after('direction_id');
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->integer('wheelchair_boarding')->nullable()->after('stop_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('wheelchair_accessible');
        });

        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn('wheelchair_boarding');
        });
    }
};
