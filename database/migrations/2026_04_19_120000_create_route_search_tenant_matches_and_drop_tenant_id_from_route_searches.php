<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('route_searches', 'tenant_id')) {
            Schema::table('route_searches', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('route_searches', 'tenant_id')) {
            Schema::table('route_searches', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('user_id');
            });
        }
    }
};
