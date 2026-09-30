<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_code', 64)->unique()->index();
            $table->foreignId('pickup_assignment_id')->unique()->constrained('pickup_assignments')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_amount_paise');
            $table->unsignedBigInteger('collected_amount_paise');
            $table->unsignedBigInteger('shortfall_amount_paise')->default(0);
            $table->boolean('is_partial')->default(false);
            $table->timestamp('collector_approved_at');
            $table->timestamp('retailer_confirmed_at')->nullable();
            $table->enum('status', [
                'PENDING_RETAILER_CONFIRMATION',
                'CONFIRMED',
                'DISPUTED',
                'CANCELLED'
            ])->default('PENDING_RETAILER_CONFIRMATION')->index();
            $table->timestamps();
        });

        Schema::create('cash_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_collection_id')->unique()->constrained('cash_collections')->cascadeOnDelete();
            $table->unsignedInteger('count_500')->default(0);
            $table->unsignedInteger('count_200')->default(0);
            $table->unsignedInteger('count_100')->default(0);
            $table->unsignedInteger('count_50')->default(0);
            $table->unsignedInteger('count_20')->default(0);
            $table->unsignedInteger('count_10')->default(0);
            $table->unsignedInteger('count_coins')->default(0);
            $table->unsignedInteger('total_notes')->default(0);
            $table->unsignedBigInteger('calculated_amount_paise')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_denominations');
        Schema::dropIfExists('cash_collections');
    }
};
