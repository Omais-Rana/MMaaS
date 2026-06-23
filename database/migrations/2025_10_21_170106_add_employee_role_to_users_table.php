<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'employee' and remove 'customer' from the constraint
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'subadmin', 'employee', 'customer') DEFAULT 'customer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'subadmin', 'customer') DEFAULT 'customer'");
    }
};
