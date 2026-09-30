<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->unique()->constrained('retailers')->cascadeOnDelete();
            $table->unsignedBigInteger('balance_paise')->default(0);
            $table->unsignedBigInteger('locked_balance_paise')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref', 64)->unique()->index();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained('retailers')->cascadeOnDelete();
            $table->enum('type', [
                'CREDIT_CASH_COLLECTION',
                'DEBIT_RECHARGE',
                'DEBIT_BBPS',
                'DEBIT_PENALTY',
                'CREDIT_WAIVER_REFUND',
                'CREDIT_MANUAL_ADJUSTMENT',
                'DEBIT_MANUAL_ADJUSTMENT'
            ])->index();
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('opening_balance_paise');
            $table->unsignedBigInteger('closing_balance_paise');
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED', 'REVERSED'])->default('PENDING')->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('idempotency_key', 100)->unique()->nullable()->index();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
