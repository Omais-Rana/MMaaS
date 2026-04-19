<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('route_search_tenant_matches');
    }

    public function down(): void
    {
        if (! Schema::hasTable('route_search_tenant_matches')) {
            Schema::create('route_search_tenant_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_search_id')->constrained('route_searches')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['route_search_id', 'tenant_id']);
                $table->index('tenant_id');
            });
        }
    }
};
