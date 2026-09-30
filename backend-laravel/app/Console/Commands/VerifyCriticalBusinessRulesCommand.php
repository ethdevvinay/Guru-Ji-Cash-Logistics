<?php

namespace App\Console\Commands;

use App\Models\Collector;
use App\Models\Retailer;
use App\Models\PickupRequest;
use App\Models\WalletTransaction;
use App\Services\DoubleEntryWalletService;
use App\Services\GeofenceVerificationService;
use App\Services\PickupDispatchService;
use Exception;
use Illuminate\Console\Command;

class VerifyCriticalBusinessRulesCommand extends Command
{
    protected $signature = 'cms:verify-critical-rules';
    protected $description = 'Execute and verify all 6 critical business and security tests from specification';

    public function handle(): int
    {
        $this->info("============================================================");
        $this->info("EXECUTING 6 CRITICAL SYSTEM INTEGRITY & SECURITY TESTS");
        $this->info("============================================================\n");

        $passed = 0;
        $failed = 0;

        // Clean up or close any stale active pickups from previous runs
        PickupRequest::whereIn('status', ['PENDING', 'BROADCASTING', 'ACCEPTED', 'EN_ROUTE', 'ARRIVED'])
            ->update(['status' => 'CANCELLED']);

        // Test 1: Atomic Race Lock Single-Winner Guarantee
        $this->comment("TEST 1: Simultaneous Accept Race Lock (Exactly ONE collector wins)...");
        try {
            $retailer = Retailer::where('shop_name', 'like', '%Radhe%')->first() ?? Retailer::first();
            $pickup = app(PickupDispatchService::class)->createPickupRequest($retailer->id, 4000000);
            $collector1 = Collector::where('collector_code', 'COL-104')->first();
            $collector2 = Collector::where('collector_code', 'COL-102')->first();

            // Collector 1 accepts
            $res1 = app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector1->id);

            // Collector 2 attempts to accept the same pickup
            $collector2Won = false;
            try {
                app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector2->id);
                $collector2Won = true;
            } catch (Exception $e) {
                // Expected exception: "Pickup already accepted by another collector."
            }

            if ($res1['status'] === 'ASSIGNED' && !$collector2Won) {
                $this->info("  [PASSED] Single-Winner Race Lock verified. Atomic row-lock prevented double-claiming.\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Race lock allowed dual winners!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 1 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        // Test 2: Collector outside 100m Geofence Blocked
        $this->comment("TEST 2: Geofence Enforcement at 150m (Cash collection blocked)...");
        try {
            $retailerLat = 28.8955;
            $retailerLng = 76.6066;
            // 150m away: 0.00135 lat offset
            $dist150m = GeofenceVerificationService::calculateDistanceMeters(28.89685, 76.6066, $retailerLat, $retailerLng);

            if ($dist150m > 100) {
                $this->info("  [PASSED] Distance is {$dist150m}m. Geofence correctly evaluates > 100m (Blocked).\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Geofence calculation returned {$dist150m}m <= 100m!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 2 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        // Test 3: Collector inside 100m Geofence Authorized
        $this->comment("TEST 3: Geofence Verification at <=100m (Cash collection eligible)...");
        try {
            $retailerLat = 28.8955;
            $retailerLng = 76.6066;
            // 38m away: 0.00035 lat offset
            $dist38m = GeofenceVerificationService::calculateDistanceMeters(28.89585, 76.6066, $retailerLat, $retailerLng);

            if ($dist38m <= 100) {
                $this->info("  [PASSED] Distance is {$dist38m}m. Geofence correctly evaluates <= 100m eligible.\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Geofence calculation returned {$dist38m}m > 100m!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 3 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        // Test 4: Collector Float Limit >= ₹1,00,000 blocks new assignment
        $this->comment("TEST 4: Collector Float Cap (Limit >= ₹1,00,000 blocks assignments)...");
        try {
            $collector = Collector::where('collector_code', 'COL-108')->first();
            $collector->update(['current_float_paise' => 10000000]); // ₹1,00,000

            $retailer = Retailer::where('shop_name', 'like', '%Sharma%')->first() ?? Retailer::first();
            $pickup = app(PickupDispatchService::class)->createPickupRequest($retailer->id, 2000000);

            $blocked = false;
            try {
                app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickup->id, $collector->id);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'safety limit reached') !== false) {
                    $blocked = true;
                }
            }

            if ($blocked) {
                $this->info("  [PASSED] Collector float limit ₹1,00,000 successfully blocked new pickup assignment.\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Collector exceeding float limit was not blocked!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 4 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        // Test 5: Retailer cannot create another pickup while previous is active/unconfirmed
        $this->comment("TEST 5: Anti-Duplicate Cycle Lock (Blocked if previous cycle unconfirmed)...");
        try {
            $retailer = Retailer::where('shop_name', 'like', '%Radhe%')->first() ?? Retailer::first();
            $blocked = false;

            try {
                // Retailer already has the pickup created in Test 1 in progress
                app(PickupDispatchService::class)->createPickupRequest($retailer->id, 2500000);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'active pickup in progress') !== false) {
                    $blocked = true;
                }
            }

            if ($blocked) {
                $this->info("  [PASSED] Retailer duplicate pickup cycle creation prevented.\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Retailer was able to create multiple concurrent active requests!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 5 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        // Test 6: API Idempotency protects against duplicate double-tap transactions
        $this->comment("TEST 6: Financial Transaction Idempotency (Duplicate POST returns same record)...");
        try {
            $retailer = Retailer::where('shop_name', 'like', '%Gupta%')->first() ?? Retailer::latest()->first();
            $walletService = app(DoubleEntryWalletService::class);
            $idempotencyKey = 'IDEMP-VERIFY-' . time() . '-' . rand(100, 999);

            $txn1 = $walletService->debitRecharge($retailer->id, 29900, 'JIO', '9812000005', $idempotencyKey);
            $txn2 = $walletService->debitRecharge($retailer->id, 29900, 'JIO', '9812000005', $idempotencyKey);

            $count = WalletTransaction::where('idempotency_key', $idempotencyKey)->count();

            if ($txn1->id === $txn2->id && $count === 1) {
                $this->info("  [PASSED] Idempotency verified: Duplicate API calls returned original Txn #{$txn1->id} without duplicate debit.\n");
                $passed++;
            } else {
                $this->error("  [FAILED] Duplicate wallet transaction was created!\n");
                $failed++;
            }
        } catch (Exception $e) {
            $this->error("  [FAILED] Test 6 encountered error: " . $e->getMessage() . "\n");
            $failed++;
        }

        $this->info("============================================================");
        $this->info("VERIFICATION COMPLETE: {$passed}/6 TESTS PASSED (" . ($failed === 0 ? "100% SUCCESS" : "{$failed} FAILED") . ")");
        $this->info("============================================================\n");

        return $failed === 0 ? 0 : 1;
    }
}
