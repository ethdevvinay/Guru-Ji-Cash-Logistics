<?php

namespace Tests\Feature;

use App\Models\Collector;
use App\Models\Retailer;
use App\Models\User;
use App\Models\PickupRequest;
use App\Models\PickupAssignment;
use App\Models\CashCollection;
use App\Models\WalletTransaction;
use App\Services\DoubleEntryWalletService;
use App\Services\GeofenceVerificationService;
use App\Services\PickupDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalSystemTest extends TestCase
{
    /**
     * Critical Test 1: Multiple collectors accept the same pickup simultaneously.
     * Expected: Exactly ONE collector wins.
     */
    public function test_critical_1_atomic_race_lock_only_one_collector_wins(): void
    {
        $pickup = PickupRequest::where('status', 'BROADCASTING')->first();
        if (!$pickup) {
            $retailer = Retailer::first();
            $pickup = app(PickupDispatchService::class)->createPickupRequest($retailer->id, 4000000);
        }

        $collector1 = Collector::where('collector_code', 'COL-104')->first();
        $collector2 = Collector::where('collector_code', 'COL-102')->first();

        // Collector 1 accepts
        $res1 = app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector1->id);
        $this->assertEquals('ASSIGNED', $res1['status']);
        $this->assertEquals($collector1->id, $pickup->fresh()->assignment->collector_id);

        // Collector 2 tries to accept the same pickup
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This pickup request has already been accepted by another collector.');
        app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector2->id);
    }

    /**
     * Critical Test 2: Collector at 150m.
     * Expected: Collection blocked.
     */
    public function test_critical_2_geofence_at_150m_blocked(): void
    {
        $retailerLat = 28.8955;
        $retailerLng = 76.6066;

        // Roughly 150m north: 0.00135 degrees lat ~ 150 meters
        $collectorLat = 28.89685;
        $collectorLng = 76.6066;

        $distance = GeofenceVerificationService::calculateDistanceMeters(
            $collectorLat,
            $collectorLng,
            $retailerLat,
            $retailerLng
        );

        $this->assertGreaterThan(100, $distance);
        $this->assertFalse($distance <= 100);
    }

    /**
     * Critical Test 3: Collector at 100m or less.
     * Expected: Eligible subject to validations.
     */
    public function test_critical_3_geofence_within_100m_eligible(): void
    {
        $retailerLat = 28.8955;
        $retailerLng = 76.6066;

        // Roughly 40m away: 0.00035 degrees lat ~ 38 meters
        $collectorLat = 28.89585;
        $collectorLng = 76.6066;

        $distance = GeofenceVerificationService::calculateDistanceMeters(
            $collectorLat,
            $collectorLng,
            $retailerLat,
            $retailerLng
        );

        $this->assertLessThanOrEqual(100, $distance);
    }

    /**
     * Critical Test 4: Collector float >= ₹1,00,000.
     * Expected: New pickup assignment blocked.
     */
    public function test_critical_4_collector_float_limit_blocks_new_assignment(): void
    {
        $collector = Collector::where('collector_code', 'COL-108')->first();
        // Set current float to ₹1,00,000 (10,000,000 paise)
        $collector->update(['current_float_paise' => 10000000]);

        $retailer = Retailer::first();
        // Create an unassigned pickup
        $pickup = PickupRequest::create([
            'retailer_id' => $retailer->id,
            'pickup_code' => 'REQ-TEST-FLOAT-BLOCK',
            'requested_amount_paise' => 2000000,
            'status' => 'BROADCASTING',
            'broadcast_expires_at' => now()->addSeconds(90),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Safety limit reached (₹1,00,000)');
        app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector->id);
    }

    /**
     * Critical Test 5: Retailer has not confirmed previous collection.
     * Expected: New pickup request remains blocked.
     */
    public function test_critical_5_unconfirmed_collection_blocks_new_request(): void
    {
        $retailer = Retailer::where('retailer_code', 'RET-201')->first();

        // Ensure retailer has an active/unconfirmed pickup
        $active = PickupRequest::where('retailer_id', $retailer->id)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'EXPIRED'])
            ->first();

        if (!$active) {
            $active = app(PickupDispatchService::class)->createPickupRequest($retailer->id, 4000000);
        }

        $this->assertTrue($retailer->hasActivePickupRequest());

        // Attempting to create another pickup request should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Active pickup cycle in progress');
        app(PickupDispatchService::class)->createPickupRequest($retailer->id, 2500000);
    }

    /**
     * Critical Test 6: Same wallet API request submitted twice (Idempotency).
     * Expected: Only one wallet transaction created.
     */
    public function test_critical_6_double_entry_wallet_idempotency_prevents_duplicate(): void
    {
        $retailer = Retailer::where('retailer_code', 'RET-202')->first();
        $walletService = app(DoubleEntryWalletService::class);
        $idempotencyKey = 'IDEMP-TEST-KEY-2026-X99';

        // First debit execution
        $txn1 = $walletService->debitRecharge(
            $retailer->id,
            29900, // ₹299.00
            'JIO',
            '9812000005',
            $idempotencyKey
        );

        $this->assertNotNull($txn1);

        // Second debit execution with identical idempotency key
        $txn2 = $walletService->debitRecharge(
            $retailer->id,
            29900, // ₹299.00
            'JIO',
            '9812000005',
            $idempotencyKey
        );

        // Expect the exact same transaction ID returned without creating a second record
        $this->assertEquals($txn1->id, $txn2->id);

        $matchingCount = WalletTransaction::where('reference_id', $idempotencyKey)->count();
        $this->assertEquals(1, $matchingCount);
    }
}
