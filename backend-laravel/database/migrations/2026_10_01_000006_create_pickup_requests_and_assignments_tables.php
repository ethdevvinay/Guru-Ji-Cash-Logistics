<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 64)->unique()->index();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_amount_paise');
            $table->enum('status', [
                'PENDING',
                'BROADCASTING',
                'ACCEPTED',
                'EN_ROUTE',
                'ARRIVED',
                'GEOFENCE_UNLOCKED',
                'CASH_COUNTING',
                'SUBMITTED',
                'RETAILER_PENDING_CONFIRMATION',
                'CONFIRMED',
                'COMPLETED',
                'PARTIAL',
                'CANCELLED',
                'EXPIRED',
                'FAILED',
                'DISPUTED'
            ])->default('PENDING')->index();
            $table->timestamp('broadcast_started_at')->nullable();
            $table->timestamp('broadcast_expires_at')->nullable()->index();
            $table->decimal('shop_lat', 10, 8);
            $table->decimal('shop_lng', 11, 8);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pickup_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')->unique()->constrained('pickup_requests')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('geofence_verified_at')->nullable();
            $table->decimal('distance_at_arrival_m', 8, 2)->nullable();
            $table->enum('status', ['ASSIGNED', 'EN_ROUTE', 'ARRIVED', 'IN_COUNT', 'COMPLETED', 'CANCELLED'])->default('ASSIGNED');
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_assignments');
        Schema::dropIfExists('pickup_requests');
    }
};
