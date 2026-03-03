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
        Schema::table('shapes', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_import_id')->nullable()->after('id')->index();
        });

        Schema::table('schedule_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('total_shapes')->nullable()->after('total_trips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shapes', function (Blueprint $table) {
            $table->dropColumn('schedule_import_id');
        });

        Schema::table('schedule_imports', function (Blueprint $table) {
            $table->dropColumn('total_shapes');
        });
    }
};
