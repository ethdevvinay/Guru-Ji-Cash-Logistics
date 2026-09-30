<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('collector_code', 50)->unique();
            $table->string('bike_number', 50)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->unsignedBigInteger('float_limit_paise')->default(10000000); // ₹1,00,000 default
            $table->unsignedBigInteger('current_float_paise')->default(0);
            $table->enum('duty_status', ['OFF_DUTY', 'ON_DUTY', 'ON_JOB', 'EMERGENCY'])->default('OFF_DUTY')->index();
            $table->unsignedTinyInteger('battery_percent')->default(100);
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('retailers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('shop_name', 191)->index();
            $table->string('owner_name', 150);
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedBigInteger('outstanding_paise')->default(0);
            $table->string('gstin', 20)->nullable();
            $table->string('pan_number', 15)->nullable();
            $table->boolean('is_kyc_verified')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_outstanding_alert_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('collectors');
    }
};
