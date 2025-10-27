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
        // Schedule imports tracking
        Schema::create('schedule_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->string('filename');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->integer('total_routes')->default(0);
            $table->integer('total_stops')->default(0);
            $table->integer('total_trips')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Routes (bus lines, train lines, etc.)
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_import_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('route_id')->index(); // Original ID from GTFS
            $table->string('route_short_name')->nullable();
            $table->string('route_long_name');
            $table->string('route_type'); // bus, train, ferry, etc.
            $table->string('route_color')->nullable();
            $table->string('route_text_color')->nullable();
            $table->text('route_desc')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'route_id']);
        });

        // Stops (stations, bus stops, etc.)
        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_import_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('stop_id')->index(); // Original ID from GTFS
            $table->string('stop_name');
            $table->text('stop_desc')->nullable();
            $table->decimal('stop_lat', 10, 8);
            $table->decimal('stop_lon', 11, 8);
            $table->string('zone_id')->nullable();
            $table->string('stop_code')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'stop_id']);
        });

        // Trips
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->foreignId('schedule_import_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('trip_id')->index(); // Original ID from GTFS
            $table->string('service_id')->index();
            $table->string('trip_headsign')->nullable();
            $table->string('trip_short_name')->nullable();
            $table->integer('direction_id')->default(0); // 0 or 1
            $table->timestamps();

            $table->unique(['tenant_id', 'trip_id']);
        });

        // Stop times (when a trip stops at a stop)
        Schema::create('stop_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('stop_id')->constrained()->onDelete('cascade');
            $table->time('arrival_time');
            $table->time('departure_time');
            $table->integer('stop_sequence');
            $table->string('stop_headsign')->nullable();
            $table->integer('pickup_type')->default(0);
            $table->integer('drop_off_type')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'stop_sequence']);
        });

        // Calendar (service days)
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_import_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('service_id')->index();
            $table->boolean('monday')->default(false);
            $table->boolean('tuesday')->default(false);
            $table->boolean('wednesday')->default(false);
            $table->boolean('thursday')->default(false);
            $table->boolean('friday')->default(false);
            $table->boolean('saturday')->default(false);
            $table->boolean('sunday')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['tenant_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stop_times');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('stops');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('calendars');
        Schema::dropIfExists('schedule_imports');
    }
};
