<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('current_cash_paise')->default(0);
            $table->timestamps();
        });

        Schema::create('vault_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 64)->unique()->index();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('total_cash_counted_paise')->default(0);
            $table->unsignedInteger('total_collectors_settled')->default(0);
            $table->enum('status', ['OPEN', 'CLOSED', 'DEPOSITED_TO_BANKS'])->default('OPEN')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vault_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_batch_id')->constrained('vault_batches')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_handed_over_paise');
            $table->json('denomination_breakdown')->nullable();
            $table->unsignedBigInteger('collector_float_before_paise');
            $table->unsignedBigInteger('collector_float_after_paise')->default(0);
            $table->foreignId('digital_signoff_by_admin_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('verified_by_cash_machine')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 100);
            $table->string('account_name', 150);
            $table->string('account_number_masked', 50);
            $table->string('ifsc_code', 20);
            $table->string('branch', 100)->nullable();
            $table->enum('account_type', ['CURRENT', 'OD'])->default('CURRENT');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_batch_id')->constrained('vault_batches')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('allocated_amount_paise');
            $table->date('deposit_date');
            $table->string('utr_number', 100)->unique()->nullable()->index();
            $table->string('deposit_slip_url', 255)->nullable();
            $table->enum('reconciliation_status', ['PENDING', 'MATCHED', 'DISCREPANCY'])->default('PENDING')->index();
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_deposits');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('vault_transactions');
        Schema::dropIfExists('vault_batches');
        Schema::dropIfExists('vaults');
    }
};
