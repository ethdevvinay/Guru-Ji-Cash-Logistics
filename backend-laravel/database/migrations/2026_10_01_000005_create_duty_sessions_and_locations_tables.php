<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->timestamp('punch_in_at');
            $table->decimal('punch_in_lat', 10, 8);
            $table->decimal('punch_in_lng', 11, 8);
            $table->decimal('punch_in_odometer_km', 8, 2)->nullable();
            $table->timestamp('punch_out_at')->nullable();
            $table->decimal('punch_out_lat', 10, 8)->nullable();
            $table->decimal('punch_out_lng', 11, 8)->nullable();
            $table->decimal('punch_out_odometer_km', 8, 2)->nullable();
            $table->unsignedBigInteger('total_collected_paise')->default(0);
            $table->enum('status', ['ACTIVE', 'COMPLETED', 'TERMINATED'])->default('ACTIVE')->index();
            $table->timestamps();
        });

        Schema::create('collector_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed_kmh', 5, 2)->default(0);
            $table->unsignedTinyInteger('battery_percent')->default(100);
            $table->decimal('accuracy_meters', 6, 2)->default(0);
            $table->boolean('is_mock_detected')->default(false);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });

        Schema::create('gps_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_events');
        Schema::dropIfExists('collector_locations');
        Schema::dropIfExists('duty_sessions');
    }
};
