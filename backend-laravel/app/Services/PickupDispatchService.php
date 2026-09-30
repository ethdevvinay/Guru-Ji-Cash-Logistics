<?php

namespace App\Services;

use App\Models\CashCollection;
use App\Models\Collector;
use App\Models\Penalty;
use App\Models\PickupAssignment;
use App\Models\PickupRequest;
use App\Models\Retailer;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PickupDispatchService
{
    /**
     * Create on-demand pickup request with Anti-Duplicate Lock.
     */
    public function createPickupRequest(int $retailerId, int $amountPaise, ?string $notes = null): PickupRequest
    {
        return DB::transaction(function () use ($retailerId, $amountPaise, $notes) {
            $retailer = Retailer::where('id', $retailerId)->lockForUpdate()->firstOrFail();

            // Strict Rule: A retailer cannot create another pickup request while previous cycle is incomplete
            if ($retailer->hasActivePickupRequest()) {
                throw new Exception("You already have an active pickup in progress. Please complete or cancel it before raising a new request.");
            }

            $broadcastDuration = Setting::get('broadcast_countdown_seconds', 90);
            $requestCode = 'REQ-' . date('Ymd') . '-' . Str::upper(Str::random(6));

            $pickup = PickupRequest::create([
                'request_code'           => $requestCode,
                'retailer_id'             => $retailer->id,
                'zone_id'                 => $retailer->zone_id,
                'requested_amount_paise' => $amountPaise,
                'status'                 => 'BROADCASTING',
                'broadcast_started_at'   => now(),
                'broadcast_expires_at'   => now()->addSeconds($broadcastDuration),
                'shop_lat'               => $retailer->latitude,
                'shop_lng'               => $retailer->longitude,
                'notes'                  => $notes,
            ]);

            return $pickup;
        });
    }

    /**
     * First-To-Accept Race Lock (Server-Side Guaranteed Exactly-One-Winner).
     */
    public function acceptPickupWithRaceLock(int $pickupRequestId, int $collectorId): PickupAssignment
    {
        return DB::transaction(function () use ($pickupRequestId, $collectorId) {
            // 1. Pessimistic row-level lock on the request
            $pickup = PickupRequest::where('id', $pickupRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validate current operational state
            if (!in_array($pickup->status, ['PENDING', 'BROADCASTING'], true)) {
                throw new Exception("Pickup already accepted by another collector.");
            }

            // 3. Validate 90s countdown
            if ($pickup->broadcast_expires_at && $pickup->broadcast_expires_at->isPast()) {
                $pickup->status = 'EXPIRED';
                $pickup->save();
                throw new Exception("The 90-second acceptance countdown has expired.");
            }

            // 4. Validate Collector Float Safety Cap (₹1,00,000)
            $collector = Collector::where('id', $collectorId)->lockForUpdate()->firstOrFail();
            $maxFloatPaise = $collector->float_limit_paise;

            if (($collector->current_float_paise + $pickup->requested_amount_paise) > $maxFloatPaise) {
                throw new Exception(
                    "Cash bag safety limit reached! Current float: ₹" . ($collector->current_float_paise / 100) .
                    ". Deposit cash at Central Vault Desk to accept more jobs."
                );
            }

            // 5. Atomic state transition
            $pickup->status = 'ACCEPTED';
            $pickup->save();

            // 6. Bind assignment
            $assignment = PickupAssignment::create([
                'pickup_request_id' => $pickup->id,
                'collector_id'      => $collector->id,
                'accepted_at'       => now(),
                'status'            => 'ASSIGNED',
            ]);

            // Update collector duty state
            $collector->duty_status = 'ON_JOB';
            $collector->save();

            return $assignment;
        });
    }

    /**
     * Cancel pickup request with 3-minute free window & ₹50 penalty rules.
     */
    public function cancelPickup(int $pickupRequestId, int $retailerId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($pickupRequestId, $retailerId, $reason) {
            $pickup = PickupRequest::where('id', $pickupRequestId)
                ->where('retailer_id', $retailerId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($pickup->status, ['COMPLETED', 'CANCELLED', 'CONFIRMED'], true)) {
                throw new Exception("Pickup request cannot be cancelled in status: {$pickup->status}");
            }

            $createdAt = $pickup->created_at;
            $freeWindowSeconds = Setting::get('free_cancellation_seconds', 180); // 3 minutes
            $penaltyApplied = false;
            $penalty = null;

            // Check if cancelled after 3 minutes and assignment was already active
            if (now()->diffInSeconds($createdAt) > $freeWindowSeconds && $pickup->assignment) {
                $penaltyPaise = Setting::get('cancellation_penalty_paise', 5000); // ₹50
                $collectorShare = Setting::get('penalty_collector_share_paise', 3500); // ₹35
                $adminShare = Setting::get('penalty_admin_share_paise', 1500); // ₹15

                $penalty = Penalty::create([
                    'retailer_id'           => $retailerId,
                    'pickup_request_id'     => $pickup->id,
                    'amount_paise'          => $penaltyPaise,
                    'collector_share_paise' => $collectorShare,
                    'admin_share_paise'     => $adminShare,
                    'reason'                => $reason ?? 'Cancelled after 3-minute free window',
                    'status'                => 'APPLIED',
                ]);

                // Debit wallet
                app(DoubleEntryWalletService::class)->debitPenalty($retailerId, $penaltyPaise, $penalty->id);
                $penaltyApplied = true;
            }

            $pickup->status = 'CANCELLED';
            $pickup->save();

            if ($pickup->assignment) {
                $pickup->assignment->update([
                    'status' => 'CANCELLED',
                    'cancellation_reason' => $reason ?? 'Cancelled by Retailer',
                ]);

                // Reset collector duty
                $pickup->assignment->collector->update(['duty_status' => 'ON_DUTY']);
            }

            return [
                'pickup' => $pickup,
                'penalty_applied' => $penaltyApplied,
                'penalty' => $penalty,
            ];
        });
    }

    /**
     * Retailer [ACCEPT & CONFIRM] - 1-Tap Finalizes Wallet Credit & Unlocks Next Request.
     */
    public function retailerAcceptAndConfirm(int $cashCollectionId, int $retailerId): array
    {
        return DB::transaction(function () use ($cashCollectionId, $retailerId) {
            $collection = CashCollection::where('id', $cashCollectionId)
                ->where('retailer_id', $retailerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($collection->status === 'CONFIRMED') {
                return ['collection' => $collection, 'message' => 'Already confirmed.'];
            }

            // 1. Finalize double-entry wallet credit
            $walletTxn = app(DoubleEntryWalletService::class)->finalizeCollectionWalletCredit($collection);

            // 2. Mark collection confirmed
            $collection->status = 'CONFIRMED';
            $collection->retailer_confirmed_at = now();
            $collection->save();

            // 3. Complete pickup assignment and request
            $assignment = $collection->assignment;
            $assignment->status = 'COMPLETED';
            $assignment->save();

            $pickup = $assignment->pickupRequest;
            $pickup->status = 'COMPLETED';
            $pickup->save();

            // 4. Return collector to ON_DUTY
            $assignment->collector->update(['duty_status' => 'ON_DUTY']);

            return [
                'collection' => $collection,
                'wallet_transaction' => $walletTxn,
                'message' => 'Collection confirmed and ₹' . ($collection->collected_amount_paise / 100) . ' credited to wallet instantly!',
            ];
        });
    }
}
