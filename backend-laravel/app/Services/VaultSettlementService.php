<?php

namespace App\Services;

use App\Models\BankDeposit;
use App\Models\Collector;
use App\Models\Vault;
use App\Models\VaultBatch;
use App\Models\VaultTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

class VaultSettlementService
{
    /**
     * Settle physical cash collected by collector at Central Vault Desk and reset float to ₹0.
     */
    public function settleEveningCollectorHandover(
        int $vaultBatchId,
        int $collectorId,
        int $amountPaise,
        array $denominations,
        int $adminUserId
    ): VaultTransaction {
        return DB::transaction(function () use ($vaultBatchId, $collectorId, $amountPaise, $denominations, $adminUserId) {
            $batch = VaultBatch::where('id', $vaultBatchId)->lockForUpdate()->firstOrFail();
            $collector = Collector::where('id', $collectorId)->lockForUpdate()->firstOrFail();

            if ($collector->current_float_paise < $amountPaise) {
                throw new Exception("Collector only holds ₹" . ($collector->current_float_paise / 100) . " in bag float.");
            }

            $floatBefore = $collector->current_float_paise;
            $floatAfter = $floatBefore - $amountPaise;

            // 1. Create Vault Transaction record
            $vaultTxn = VaultTransaction::create([
                'vault_batch_id'             => $batch->id,
                'collector_id'               => $collector->id,
                'amount_handed_over_paise'   => $amountPaise,
                'denomination_breakdown'     => $denominations,
                'collector_float_before_paise' => $floatBefore,
                'collector_float_after_paise'  => $floatAfter,
                'digital_signoff_by_admin_id' => $adminUserId,
                'verified_by_cash_machine'   => true,
            ]);

            // 2. Reset / reduce collector float
            $collector->current_float_paise = $floatAfter;
            $collector->save();

            // 3. Increment batch cash and vault cash
            $batch->total_cash_counted_paise += $amountPaise;
            $batch->total_collectors_settled += 1;
            $batch->save();

            $vault = Vault::first();
            if ($vault) {
                $vault->current_cash_paise += $amountPaise;
                $vault->save();
            }

            return $vaultTxn;
        });
    }

    /**
     * Allocate vault batch cash into multi-bank accounts (e.g. ₹3L HDFC + ₹1.85L SBI).
     */
    public function allocateBankDeposits(int $vaultBatchId, array $allocations, int $adminUserId): array
    {
        return DB::transaction(function () use ($vaultBatchId, $allocations, $adminUserId) {
            $batch = VaultBatch::where('id', $vaultBatchId)->lockForUpdate()->firstOrFail();

            $totalAllocatedPaise = 0;
            $createdDeposits = [];

            foreach ($allocations as $alloc) {
                $bankAccountId = (int) $alloc['bank_account_id'];
                $amountPaise = (int) $alloc['amount_paise'];
                $utrNumber = $alloc['utr_number'] ?? null;
                $depositSlipUrl = $alloc['deposit_slip_url'] ?? null;

                $deposit = BankDeposit::create([
                    'vault_batch_id'        => $batch->id,
                    'bank_account_id'       => $bankAccountId,
                    'allocated_amount_paise'=> $amountPaise,
                    'deposit_date'          => $alloc['deposit_date'] ?? now()->toDateString(),
                    'utr_number'            => $utrNumber,
                    'deposit_slip_url'      => $depositSlipUrl,
                    'reconciliation_status' => $utrNumber ? 'MATCHED' : 'PENDING',
                    'verified_by_admin_id'  => $adminUserId,
                ]);

                $totalAllocatedPaise += $amountPaise;
                $createdDeposits[] = $deposit;
            }

            if ($totalAllocatedPaise > $batch->total_cash_counted_paise) {
                throw new Exception("Allocated amount (₹" . ($totalAllocatedPaise / 100) . ") exceeds batch cash (₹" . ($batch->total_cash_counted_paise / 100) . ")");
            }

            $batch->status = 'DEPOSITED_TO_BANKS';
            $batch->closed_at = now();
            $batch->save();

            return $createdDeposits;
        });
    }
}
