<?php

namespace App\Services;

use InvalidArgumentException;

class CashDenominationService
{
    public const DENOMINATION_VALUES_PAISE = [
        'count_500' => 50000,
        'count_200' => 20000,
        'count_100' => 10000,
        'count_50'  => 5000,
        'count_20'  => 2000,
        'count_10'  => 1000,
    ];

    /**
     * Calculate and strictly validate denomination count against collected amount in paise.
     */
    public function validateAndCalculate(array $denominations, int $collectedAmountPaise): array
    {
        $calculatedTotalPaise = 0;
        $totalNotes = 0;
        $sanitized = [];

        foreach (self::DENOMINATION_VALUES_PAISE as $key => $valuePaise) {
            $count = max(0, (int) ($denominations[$key] ?? 0));
            $sanitized[$key] = $count;
            $calculatedTotalPaise += ($count * $valuePaise);
            $totalNotes += $count;
        }

        $coinsInRupees = max(0, (int) ($denominations['count_coins'] ?? 0));
        $coinsPaise = $coinsInRupees * 100;
        $sanitized['count_coins'] = $coinsInRupees;
        $calculatedTotalPaise += $coinsPaise;

        if ($calculatedTotalPaise !== $collectedAmountPaise) {
            $enteredRupees = $collectedAmountPaise / 100;
            $calculatedRupees = $calculatedTotalPaise / 100;
            throw new InvalidArgumentException(
                "Denomination breakdown sum (₹{$calculatedRupees}) does not match entered total (₹{$enteredRupees})."
            );
        }

        return [
            'is_valid' => true,
            'denominations' => $sanitized,
            'total_notes' => $totalNotes,
            'calculated_amount_paise' => $calculatedTotalPaise,
        ];
    }
}
