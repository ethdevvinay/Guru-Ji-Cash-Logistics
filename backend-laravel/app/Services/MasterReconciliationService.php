<?php

namespace App\Services;

use App\Models\BankDeposit;
use App\Models\CashCollection;
use App\Models\Collector;
use App\Models\PickupRequest;
use App\Models\Retailer;
use App\Models\Vault;
use App\Models\WalletTransaction;

class MasterReconciliationService
{
    /**
     * Compute 360-degree Cash & Ledger Reconciliation Matrix.
     */
    public function calculateDaily360Reconciliation(?string $date = null): array
    {
        $targetDate = $date ?? now()->toDateString();

        // 1. Total Cash Collected Today (Paise)
        $cashReceivedPaise = (int) CashCollection::whereDate('created_at', $targetDate)
            ->where('status', 'CONFIRMED')
            ->sum('collected_amount_paise');

        $totalPickupsToday = CashCollection::whereDate('created_at', $targetDate)
            ->where('status', 'CONFIRMED')
            ->count();

        // 2. Total Wallet Credited Today (Paise)
        $walletCreditedPaise = (int) WalletTransaction::whereDate('created_at', $targetDate)
            ->where('type', 'CREDIT_CASH_COLLECTION')
            ->where('status', 'COMPLETED')
            ->sum('amount_paise');

        // 3. Retailer Total Udhar / Outstanding Market Float
        $retailerUdharPaise = (int) Retailer::sum('outstanding_paise');

        // 4. Cash In Transit (Held currently in field collector bags)
        $cashInTransitPaise = (int) Collector::sum('current_float_paise');

        // 5. Central Vault Cash Balance
        $vault = Vault::first();
        $vaultBalancePaise = (int) ($vault?->current_cash_paise ?? 0);

        // 6. Bank Deposits Today
        $bankDepositsPaise = (int) BankDeposit::whereDate('deposit_date', $targetDate)->sum('allocated_amount_paise');

        // 7. Active Pickups Counts
        $pendingPickupsCount = PickupRequest::whereDate('created_at', $targetDate)
            ->whereIn('status', ['PENDING', 'BROADCASTING'])
            ->count();

        $activeCollectorsCount = Collector::where('duty_status', '!=', 'OFF_DUTY')->count();
        $totalRetailersCount = Retailer::count();

        // 8. 360 Discrepancy Equation
        // Expected Cash Accounted = Cash in Transit + Vault Balance + Bank Deposits
        // Discrepancy is flagged if Cash Received does not match Wallet Credited
        $discrepancyPaise = abs($cashReceivedPaise - $walletCreditedPaise);
        $hasDiscrepancy = $discrepancyPaise > 0;

        return [
            'date' => $targetDate,
            'kpis' => [
                'cash_received_paise'     => $cashReceivedPaise,
                'cash_received_rupees'    => $cashReceivedPaise / 100,
                'wallet_credited_paise'   => $walletCreditedPaise,
                'wallet_credited_rupees'  => $walletCreditedPaise / 100,
                'retailer_udhar_paise'    => $retailerUdharPaise,
                'retailer_udhar_rupees'   => $retailerUdharPaise / 100,
                'cash_in_transit_paise'   => $cashInTransitPaise,
                'cash_in_transit_rupees'  => $cashInTransitPaise / 100,
                'today_pickups_count'     => $totalPickupsToday,
                'pending_requests_count'  => $pendingPickupsCount,
                'active_collectors_count' => $activeCollectorsCount,
                'total_retailers_count'   => $totalRetailersCount,
                'vault_balance_paise'     => $vaultBalancePaise,
                'vault_balance_rupees'    => $vaultBalancePaise / 100,
                'bank_deposits_paise'     => $bankDepositsPaise,
                'bank_deposits_rupees'    => $bankDepositsPaise / 100,
            ],
            'reconciliation' => [
                'is_balanced'             => !$hasDiscrepancy,
                'discrepancy_paise'       => $discrepancyPaise,
                'discrepancy_rupees'      => $discrepancyPaise / 100,
                'status_label'            => $hasDiscrepancy ? 'DISCREPANCY FLAGGED' : '100% BALANCED',
            ],
        ];
    }
}
