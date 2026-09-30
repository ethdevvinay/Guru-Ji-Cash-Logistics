<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectors', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('collector_code', 20)->unique();
            $table->string('employee_id', 40)->nullable()->unique();
            $table->string('vehicle_type', 20)->nullable();
            $table->string('vehicle_number', 20)->nullable();
            $table->unsignedBigInteger('float_limit_paise')->default(10000000);
            $table->string('status', 20)->default('ACTIVE');
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_mobile', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('collector_zones', function (Blueprint $table): void {
            $table->foreignId('collector_id')->constrained('collectors');
            $table->foreignId('zone_id')->constrained('zones');
            $table->boolean('is_primary')->default(false);
            $table->primary(['collector_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_zones');
        Schema::dropIfExists('collectors');
    }
};
