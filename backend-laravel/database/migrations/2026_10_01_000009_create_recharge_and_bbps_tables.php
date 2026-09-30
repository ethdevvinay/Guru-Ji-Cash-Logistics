<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharge_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->enum('operator', ['JIO', 'AIRTEL', 'VI', 'BSNL'])->index();
            $table->string('mobile_number', 20);
            $table->string('circle', 50)->nullable();
            $table->unsignedBigInteger('amount_paise');
            $table->string('operator_ref_id', 100)->nullable();
            $table->enum('status', ['PROCESSING', 'SUCCESS', 'FAILED'])->default('PROCESSING')->index();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('bbps_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->string('biller_id', 100);
            $table->enum('biller_category', ['ELECTRICITY', 'WATER', 'GAS', 'DTH', 'FASTAG'])->index();
            $table->string('consumer_number', 100);
            $table->unsignedBigInteger('amount_paise');
            $table->string('bbps_ref_id', 100)->nullable();
            $table->enum('status', ['PROCESSING', 'SUCCESS', 'FAILED'])->default('PROCESSING')->index();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bbps_transactions');
        Schema::dropIfExists('recharge_transactions');
    }
};
