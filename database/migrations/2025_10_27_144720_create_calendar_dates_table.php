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
        Schema::create('calendar_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_import_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('service_id')->index();
            $table->date('date');
            $table->integer('exception_type'); // 1 = service added, 2 = service removed
            $table->timestamps();

            $table->index(['tenant_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_dates');
    }
};
