<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->foreignId('pickup_request_id')->constrained('pickup_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise')->default(5000); // ₹50 default
            $table->unsignedBigInteger('collector_share_paise')->default(3500); // ₹35 collector petrol
            $table->unsignedBigInteger('admin_share_paise')->default(1500); // ₹15 admin
            $table->string('reason', 255);
            $table->enum('status', ['APPLIED', 'WAIVED', 'DEDUCTED'])->default('APPLIED')->index();
            $table->timestamps();
        });

        Schema::create('penalty_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penalty_id')->constrained('penalties')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('waiver_reason');
            $table->timestamp('approved_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_waivers');
        Schema::dropIfExists('penalties');
    }
};
