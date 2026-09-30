<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\CashCollection;
use App\Models\PickupRequest;
use App\Models\RechargeTransaction;
use App\Models\BbpsTransaction;
use App\Services\AuditLogService;
use App\Services\DoubleEntryWalletService;
use App\Services\GeofenceVerificationService;
use App\Services\PickupDispatchService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetailerApiController extends Controller
{
    /**
     * 1-Click On-Demand Cash Pickup Request.
     */
    public function createPickup(Request $request): JsonResponse
    {
        $amount = $request->input('amount_rupees') ?? $request->input('requested_amount_rupees');
        if (!$amount || $amount < 100) {
            return response()->json(['success' => false, 'message' => 'Minimum pickup amount is ₹100.'], 422);
        }

        $retailer = $request->user()->retailer;
        if (!$retailer) {
            return response()->json(['success' => false, 'message' => 'Retailer profile not found.'], 404);
        }

        $amountPaise = (int) round($amount * 100);

        try {
            $pickup = app(PickupDispatchService::class)->createPickupRequest(
                $retailer->id,
                $amountPaise,
                $request->input('notes')
            );

            AuditLogService::log('PICKUP_REQUESTED', 'PickupRequest', $pickup->id, null, [
                'amount_paise' => $amountPaise,
                'retailer'     => $retailer->shop_name,
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Pickup request created! Broadcasting to nearby active collectors for 90 seconds.',
                'data' => [
                    'pickup' => $pickup,
                    'pickup_code' => $pickup->pickup_code,
                    'countdown_seconds' => 90,
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['pickup' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Get Retailer Dashboard Overview.
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $retailer = $request->user()->retailer->load('wallet');
        $activePickup = PickupRequest::where('retailer_id', $retailer->id)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'EXPIRED', 'FAILED'])
            ->with(['assignment.collector.user', 'assignment.cashCollection'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved.',
            'data' => [
                'wallet_balance_rupees' => $retailer->wallet ? ($retailer->wallet->balance_paise / 100) : 0,
                'outstanding_rupees'    => $retailer->outstanding_paise / 100,
                'market_float_balance'  => $retailer->market_float_balance_paise / 100,
                'active_pickup'         => $activePickup ? [
                    'id'                      => $activePickup->id,
                    'pickup_code'             => $activePickup->pickup_code,
                    'requested_amount_rupees' => $activePickup->requested_amount_paise / 100,
                    'status'                  => $activePickup->status,
                    'collector'               => $activePickup->assignment?->collector ? [
                        'name'        => $activePickup->assignment->collector->user->name,
                        'code'        => $activePickup->assignment->collector->collector_code,
                        'bike_number' => $activePickup->assignment->collector->bike_number,
                    ] : null,
                ] : null,
            ],
            'errors' => [],
        ]);
    }

    /**
     * Get Collector Live Location for a Pickup.
     */
    public function getCollectorLocation(Request $request, int $pickupId): JsonResponse
    {
        $pickup = PickupRequest::with('assignment.collector.user')->find($pickupId);
        if (!$pickup || !$pickup->assignment || !$pickup->assignment->collector) {
            return response()->json(['success' => false, 'message' => 'No assigned collector found.'], 404);
        }

        $col = $pickup->assignment->collector;
        return response()->json([
            'success' => true,
            'message' => 'Collector telemetry retrieved.',
            'data' => [
                'collector_name' => $col->user->name,
                'collector_code' => $col->collector_code,
                'current_lat'    => $col->current_lat,
                'current_lng'    => $col->current_lng,
                'last_ping'      => $col->last_location_at,
            ],
            'errors' => [],
        ]);
    }

    /**
     * Get Active Pickup Status & Collector Live Radar.
     */
    public function getActivePickup(Request $request): JsonResponse
    {
        $retailer = $request->user()->retailer;

        $activePickup = PickupRequest::where('retailer_id', $retailer->id)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'EXPIRED', 'FAILED'])
            ->with(['assignment.collector.user', 'assignment.cashCollection.denominations'])
            ->latest()
            ->first();

        if (!$activePickup) {
            return response()->json([
                'success' => true,
                'message' => 'No active pickup in progress.',
                'data' => null,
                'errors' => [],
            ]);
        }

        $radar = null;
        if ($activePickup->assignment && $activePickup->assignment->collector) {
            $col = $activePickup->assignment->collector;
            $distMeters = null;

            if ($col->current_lat && $col->current_lng) {
                $distMeters = GeofenceVerificationService::calculateDistanceMeters(
                    (float) $col->current_lat,
                    (float) $col->current_lng,
                    (float) $retailer->latitude,
                    (float) $retailer->longitude
                );
            }

            // Approximate ETA: assuming average bike city speed 20 km/h = 333 m/min
            $etaMinutes = $distMeters ? max(1, round($distMeters / 333)) : null;

            $radar = [
                'collector_name'  => $col->user->name,
                'collector_code'  => $col->collector_code,
                'mobile'          => $col->user->mobile,
                'bike_number'     => $col->bike_number,
                'current_lat'     => $col->current_lat,
                'current_lng'     => $col->current_lng,
                'distance_meters' => $distMeters,
                'distance_display'=> $distMeters ? ($distMeters < 1000 ? "{$distMeters}m door" : round($distMeters / 1000, 1) . " km door") : 'Locating...',
                'eta_display'     => $etaMinutes ? "{$etaMinutes} min mein pahunchne wala hai" : 'Calculating ETA...',
                'duty_status'     => $col->duty_status,
                'last_ping'       => $col->last_location_at,
            ];
        }

        $pendingCollection = null;
        if ($activePickup->assignment?->cashCollection) {
            $colxn = $activePickup->assignment->cashCollection;
            $pendingCollection = [
                'id'                      => $colxn->id,
                'collection_code'         => $colxn->collection_code,
                'requested_amount_rupees' => $colxn->requested_amount_paise / 100,
                'collected_amount_rupees' => $colxn->collected_amount_paise / 100,
                'shortfall_rupees'        => $colxn->shortfall_amount_paise / 100,
                'is_partial'              => $colxn->is_partial,
                'status'                  => $colxn->status,
                'can_confirm'             => ($colxn->status === 'PENDING_RETAILER_CONFIRMATION'),
                'denominations'           => $colxn->denominations,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Active pickup status retrieved.',
            'data' => [
                'pickup'             => $activePickup,
                'collector_radar'    => $radar,
                'pending_collection' => $pendingCollection,
                'is_locked'          => true, // Lockout rule: new request blocked until confirmed
            ],
            'errors' => [],
        ]);
    }

    /**
     * Retailer [ACCEPT & CONFIRM] - 1-Tap Finalizes Wallet Reload.
     */
    public function acceptAndConfirm(Request $request, int $collectionId): JsonResponse
    {
        $retailer = $request->user()->retailer;

        try {
            $result = app(PickupDispatchService::class)->retailerAcceptAndConfirm($collectionId, $retailer->id);

            AuditLogService::log('RETAILER_CONFIRMED', 'CashCollection', $collectionId, null, [
                'retailer' => $retailer->shop_name,
            ], $request);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'collection'         => $result['collection'],
                    'wallet_transaction' => $result['wallet_transaction'],
                    'wallet_balance'     => $retailer->fresh()->wallet->balance_paise / 100,
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['confirm' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Cancel active pickup.
     */
    public function cancelPickup(Request $request, int $pickupId): JsonResponse
    {
        $retailer = $request->user()->retailer;

        try {
            $result = app(PickupDispatchService::class)->cancelPickup($pickupId, $retailer->id, $request->input('reason'));

            AuditLogService::log('PICKUP_CANCELLED', 'PickupRequest', $pickupId, null, [
                'penalty_applied' => $result['penalty_applied'],
            ], $request);

            $msg = 'Pickup request cancelled.';
            if ($result['penalty_applied']) {
                $msg .= ' A ₹50 cancellation fee was deducted (cancelled after 3 minutes).';
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $result,
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['cancel' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Get Wallet Balance & Summary.
     */
    public function getWalletBalance(Request $request): JsonResponse
    {
        $retailer = $request->user()->retailer->load('wallet');

        return response()->json([
            'success' => true,
            'message' => 'Wallet balance retrieved.',
            'data' => [
                'balance_rupees'     => $retailer->wallet ? ($retailer->wallet->balance_paise / 100) : 0,
                'outstanding_rupees' => $retailer->outstanding_paise / 100,
                'can_raise_pickup'   => !$retailer->hasActivePickupRequest(),
            ],
            'errors' => [],
        ]);
    }

    /**
     * Real-time Passbook & Khata Statement.
     */
    public function getPassbook(Request $request): JsonResponse
    {
        $retailer = $request->user()->retailer;

        $query = $retailer->walletTransactions()->latest();

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $transactions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Passbook statements retrieved.',
            'data' => $transactions,
            'errors' => [],
        ]);
    }

    /**
     * Execute Mobile Prepaid Recharge.
     */
    public function executeRecharge(Request $request): JsonResponse
    {
        $request->validate([
            'operator'       => 'required|in:JIO,AIRTEL,VI,BSNL',
            'mobile_number'  => 'required|digits:10',
            'amount_rupees'  => 'required|numeric|min:10',
            'circle'         => 'nullable|string',
        ]);

        $retailer = $request->user()->retailer;
        $amountPaise = (int) round($request->input('amount_rupees') * 100);
        $idempotencyKey = $request->header('X-Idempotency-Key');

        try {
            $walletService = app(DoubleEntryWalletService::class);
            $walletTxn = $walletService->debitRecharge(
                $retailer->id,
                $amountPaise,
                $request->input('operator'),
                $request->input('mobile_number'),
                $idempotencyKey
            );

            // Record recharge transaction
            $recharge = RechargeTransaction::create([
                'retailer_id'     => $retailer->id,
                'operator'        => $request->input('operator'),
                'mobile_number'   => $request->input('mobile_number'),
                'circle'          => $request->input('circle', 'Haryana'),
                'amount_paise'    => $amountPaise,
                'operator_ref_id' => 'OPR-' . date('YmdHis') . '-' . rand(1000, 9999),
                'status'          => 'SUCCESS',
            ]);

            AuditLogService::log('RECHARGE_SUCCESS', 'RechargeTransaction', $recharge->id, null, [
                'operator' => $recharge->operator,
                'mobile'   => $recharge->mobile_number,
                'amount'   => $amountPaise,
            ], $request);

            return response()->json([
                'success' => true,
                'message' => "Recharge of ₹{$request->input('amount_rupees')} for {$request->input('mobile_number')} completed successfully.",
                'data' => [
                    'recharge'       => $recharge,
                    'wallet_balance' => $retailer->fresh()->wallet->balance_paise / 100,
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['recharge' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Pay BBPS Utility Bill.
     */
    public function payBbpsBill(Request $request): JsonResponse
    {
        $request->validate([
            'biller_id'        => 'required|string',
            'biller_category'  => 'required|in:ELECTRICITY,WATER,GAS,DTH,FASTAG',
            'consumer_number'  => 'required|string',
            'amount_rupees'    => 'required|numeric|min:1',
        ]);

        $retailer = $request->user()->retailer;
        $amountPaise = (int) round($request->input('amount_rupees') * 100);
        $idempotencyKey = $request->header('X-Idempotency-Key');

        try {
            $walletService = app(DoubleEntryWalletService::class);
            $walletTxn = $walletService->debitBbps(
                $retailer->id,
                $amountPaise,
                $request->input('biller_category'),
                $request->input('consumer_number'),
                $idempotencyKey
            );

            $bbps = BbpsTransaction::create([
                'retailer_id'     => $retailer->id,
                'biller_id'       => $request->input('biller_id'),
                'biller_category' => $request->input('biller_category'),
                'consumer_number' => $request->input('consumer_number'),
                'amount_paise'    => $amountPaise,
                'bbps_ref_id'     => 'BBPS-' . date('YmdHis') . '-' . rand(1000, 9999),
                'status'          => 'SUCCESS',
            ]);

            AuditLogService::log('BBPS_BILL_PAID', 'BbpsTransaction', $bbps->id, null, [
                'category' => $bbps->biller_category,
                'consumer' => $bbps->consumer_number,
                'amount'   => $amountPaise,
            ], $request);

            return response()->json([
                'success' => true,
                'message' => "{$request->input('biller_category')} bill of ₹{$request->input('amount_rupees')} paid successfully.",
                'data' => [
                    'bbps'           => $bbps,
                    'wallet_balance' => $retailer->fresh()->wallet->balance_paise / 100,
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['bbps' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Get Active Flash Modals / Broadcasts for Retailer.
     */
    public function getBroadcasts(Request $request): JsonResponse
    {
        $broadcasts = Broadcast::whereIn('target_role', ['ALL', 'RETAILER'])
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notices retrieved.',
            'data' => ['broadcasts' => $broadcasts],
            'errors' => [],
        ]);
    }
}
