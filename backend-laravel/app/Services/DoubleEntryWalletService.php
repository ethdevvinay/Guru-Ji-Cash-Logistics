<?php

namespace App\Services;

use App\Models\CashCollection;
use App\Models\Collector;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoubleEntryWalletService
{
    /**
     * Finalize cash collection credit to retailer wallet and increment collector float.
     */
    public function finalizeCollectionWalletCredit(CashCollection $collection, ?string $idempotencyKey = null): WalletTransaction
    {
        return DB::transaction(function () use ($collection, $idempotencyKey) {
            $key = $idempotencyKey ?? "COLXN-CREDIT-{$collection->id}";

            // Check if already finalized
            $existing = WalletTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            $wallet = Wallet::where('retailer_id', $collection->retailer_id)->lockForUpdate()->firstOrFail();
            $collector = Collector::where('id', $collection->collector_id)->lockForUpdate()->firstOrFail();

            $amountPaise = $collection->collected_amount_paise;
            $openingBalance = $wallet->balance_paise;
            $closingBalance = $openingBalance + $amountPaise;

            // 1. Create immutable double-entry journal transaction
            $transaction = WalletTransaction::create([
                'transaction_ref'       => 'TXN-WT-' . date('YmdHis') . '-' . Str::upper(Str::random(6)),
                'wallet_id'             => $wallet->id,
                'retailer_id'           => $collection->retailer_id,
                'type'                  => 'CREDIT_CASH_COLLECTION',
                'amount_paise'          => $amountPaise,
                'opening_balance_paise' => $openingBalance,
                'closing_balance_paise' => $closingBalance,
                'status'                => 'COMPLETED',
                'source_id'             => $collection->id,
                'source_type'           => CashCollection::class,
                'idempotency_key'       => $key,
                'remarks'               => "Cash collection settled for {$collection->collection_code}",
            ]);

            // 2. Update wallet balance
            $wallet->balance_paise = $closingBalance;
            $wallet->save();

            // 3. Increment collector cash float
            $collector->current_float_paise += $amountPaise;
            $collector->save();

            // 4. Update retailer outstanding if applicable
            $retailer = Retailer::where('id', $collection->retailer_id)->lockForUpdate()->firstOrFail();
            if ($retailer->outstanding_paise > 0) {
                $deduction = min($retailer->outstanding_paise, $amountPaise);
                $retailer->outstanding_paise -= $deduction;
                $retailer->save();
            }

            return $transaction;
        });
    }

    /**
     * Debit wallet balance for Recharge.
     */
    public function debitRecharge(int $retailerId, int $amountPaise, string $operator, string $mobileNumber, ?string $idempotencyKey = null): WalletTransaction
    {
        return DB::transaction(function () use ($retailerId, $amountPaise, $operator, $mobileNumber, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = Wallet::where('retailer_id', $retailerId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance_paise < $amountPaise) {
                throw new Exception("Insufficient wallet balance for recharge. Balance: ₹" . ($wallet->balance_paise / 100));
            }

            $openingBalance = $wallet->balance_paise;
            $closingBalance = $openingBalance - $amountPaise;

            $transaction = WalletTransaction::create([
                'transaction_ref'       => 'TXN-RC-' . date('YmdHis') . '-' . Str::upper(Str::random(6)),
                'wallet_id'             => $wallet->id,
                'retailer_id'           => $retailerId,
                'type'                  => 'DEBIT_RECHARGE',
                'amount_paise'          => $amountPaise,
                'opening_balance_paise' => $openingBalance,
                'closing_balance_paise' => $closingBalance,
                'status'                => 'COMPLETED',
                'idempotency_key'       => $idempotencyKey,
                'remarks'               => "{$operator} recharge for {$mobileNumber}",
            ]);

            $wallet->balance_paise = $closingBalance;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Debit wallet balance for BBPS bill payment.
     */
    public function debitBbps(int $retailerId, int $amountPaise, string $category, string $consumerNumber, ?string $idempotencyKey = null): WalletTransaction
    {
        return DB::transaction(function () use ($retailerId, $amountPaise, $category, $consumerNumber, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = Wallet::where('retailer_id', $retailerId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance_paise < $amountPaise) {
                throw new Exception("Insufficient wallet balance for bill payment. Balance: ₹" . ($wallet->balance_paise / 100));
            }

            $openingBalance = $wallet->balance_paise;
            $closingBalance = $openingBalance - $amountPaise;

            $transaction = WalletTransaction::create([
                'transaction_ref'       => 'TXN-BP-' . date('YmdHis') . '-' . Str::upper(Str::random(6)),
                'wallet_id'             => $wallet->id,
                'retailer_id'           => $retailerId,
                'type'                  => 'DEBIT_BBPS',
                'amount_paise'          => $amountPaise,
                'opening_balance_paise' => $openingBalance,
                'closing_balance_paise' => $closingBalance,
                'status'                => 'COMPLETED',
                'idempotency_key'       => $idempotencyKey,
                'remarks'               => "{$category} utility payment for {$consumerNumber}",
            ]);

            $wallet->balance_paise = $closingBalance;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Debit cancellation penalty from retailer wallet.
     */
    public function debitPenalty(int $retailerId, int $penaltyPaise, int $penaltyId): WalletTransaction
    {
        return DB::transaction(function () use ($retailerId, $penaltyPaise, $penaltyId) {
            $wallet = Wallet::where('retailer_id', $retailerId)->lockForUpdate()->firstOrFail();

            $openingBalance = $wallet->balance_paise;
            $closingBalance = max(0, $openingBalance - $penaltyPaise);

            $transaction = WalletTransaction::create([
                'transaction_ref'       => 'TXN-PN-' . date('YmdHis') . '-' . Str::upper(Str::random(6)),
                'wallet_id'             => $wallet->id,
                'retailer_id'           => $retailerId,
                'type'                  => 'DEBIT_PENALTY',
                'amount_paise'          => $penaltyPaise,
                'opening_balance_paise' => $openingBalance,
                'closing_balance_paise' => $closingBalance,
                'status'                => 'COMPLETED',
                'source_id'             => $penaltyId,
                'source_type'           => \App\Models\Penalty::class,
                'remarks'               => 'Cancellation penalty deduction',
            ]);

            $wallet->balance_paise = $closingBalance;
            $wallet->save();

            return $transaction;
        });
    }
}
