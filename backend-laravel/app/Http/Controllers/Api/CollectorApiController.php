<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCollection;
use App\Models\CashDenomination;
use App\Models\Collector;
use App\Models\CollectorLocation;
use App\Models\DutySession;
use App\Models\PickupAssignment;
use App\Models\PickupRequest;
use App\Models\Receipt;
use App\Models\SosAlert;
use App\Services\AuditLogService;
use App\Services\CashDenominationService;
use App\Services\GeofenceVerificationService;
use App\Services\PickupDispatchService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectorApiController extends Controller
{
    /**
     * Punch-in duty.
     */
    public function punchIn(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'odometer_km' => 'nullable|numeric',
        ]);

        $collector = $request->user()->collector;
        if (!$collector) {
            return response()->json(['success' => false, 'message' => 'Collector profile not found.'], 404);
        }

        $session = DutySession::create([
            'collector_id'          => $collector->id,
            'punch_in_at'           => now(),
            'punch_in_lat'          => $request->input('latitude'),
            'punch_in_lng'          => $request->input('longitude'),
            'punch_in_odometer_km'  => $request->input('odometer_km'),
            'status'                => 'ACTIVE',
        ]);

        $collector->update([
            'duty_status'      => 'ON_DUTY',
            'current_lat'      => $request->input('latitude'),
            'current_lng'      => $request->input('longitude'),
            'last_location_at' => now(),
        ]);

        AuditLogService::log('DUTY_PUNCH_IN', 'Collector', $collector->id, null, ['session_id' => $session->id], $request);

        return response()->json([
            'success' => true,
            'message' => 'Duty started successfully.',
            'data' => [
                'session' => $session,
                'collector' => $collector->fresh(),
            ],
            'errors' => [],
        ]);
    }

    /**
     * Punch-out duty.
     */
    public function punchOut(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'odometer_km' => 'nullable|numeric',
        ]);

        $collector = $request->user()->collector;

        // Check if cash float has been handed over to vault
        if ($collector->current_float_paise > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot punch-out with cash in bag (Float: ₹' . ($collector->current_float_paise / 100) . '). Please complete evening vault handover first.',
                'data' => null,
                'errors' => ['float' => 'Cash float must be ₹0 to punch out.'],
            ], 422);
        }

        $session = DutySession::where('collector_id', $collector->id)
            ->where('status', 'ACTIVE')
            ->latest()
            ->first();

        if ($session) {
            $session->update([
                'punch_out_at'          => now(),
                'punch_out_lat'         => $request->input('latitude'),
                'punch_out_lng'         => $request->input('longitude'),
                'punch_out_odometer_km' => $request->input('odometer_km'),
                'status'                => 'COMPLETED',
            ]);
        }

        $collector->update([
            'duty_status'      => 'OFF_DUTY',
            'current_lat'      => $request->input('latitude'),
            'current_lng'      => $request->input('longitude'),
            'last_location_at' => now(),
        ]);

        AuditLogService::log('DUTY_PUNCH_OUT', 'Collector', $collector->id, null, ['session_id' => $session?->id], $request);

        return response()->json([
            'success' => true,
            'message' => 'Duty ended successfully.',
            'data' => ['collector' => $collector->fresh()],
            'errors' => [],
        ]);
    }

    /**
     * 10-Second Telemetry Ping.
     */
    public function streamGps(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'        => 'required|numeric',
            'longitude'       => 'required|numeric',
            'speed_kmh'       => 'nullable|numeric',
            'battery_percent' => 'nullable|integer',
            'accuracy_meters' => 'nullable|numeric',
            'is_mock'         => 'nullable|boolean',
        ]);

        $collector = $request->user()->collector;

        $collector->update([
            'current_lat'      => $request->input('latitude'),
            'current_lng'      => $request->input('longitude'),
            'battery_percent'  => $request->input('battery_percent', 100),
            'last_location_at' => now(),
        ]);

        CollectorLocation::create([
            'collector_id'     => $collector->id,
            'latitude'         => $request->input('latitude'),
            'longitude'        => $request->input('longitude'),
            'speed_kmh'        => $request->input('speed_kmh', 0),
            'battery_percent'  => $request->input('battery_percent', 100),
            'accuracy_meters'  => $request->input('accuracy_meters', 0),
            'is_mock_detected' => (bool) $request->input('is_mock', false),
            'recorded_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telemetry logged.',
            'data' => null,
            'errors' => [],
        ]);
    }

    /**
     * Get active 90s broadcasts in collector's zone.
     */
    public function getBroadcasts(Request $request): JsonResponse
    {
        $collector = $request->user()->collector;

        $broadcasts = PickupRequest::where('status', 'BROADCASTING')
            ->where('broadcast_expires_at', '>', now())
            ->where(function ($q) use ($collector) {
                if ($collector->zone_id) {
                    $q->where('zone_id', $collector->zone_id);
                }
            })
            ->with(['retailer'])
            ->get()
            ->map(function ($p) use ($collector) {
                $distanceMeters = null;
                if ($collector->current_lat && $collector->current_lng) {
                    $distanceMeters = GeofenceVerificationService::calculateDistanceMeters(
                        (float) $collector->current_lat,
                        (float) $collector->current_lng,
                        (float) $p->shop_lat,
                        (float) $p->shop_lng
                    );
                }

                return [
                    'id'               => $p->id,
                    'request_code'     => $p->request_code,
                    'shop_name'        => $p->retailer->shop_name,
                    'address'          => $p->retailer->address,
                    'requested_amount' => $p->requested_amount_paise / 100,
                    'distance_km'      => $distanceMeters ? round($distanceMeters / 1000, 1) : 0,
                    'seconds_remaining'=> max(0, now()->diffInSeconds($p->broadcast_expires_at, false)),
                    'shop_lat'         => $p->shop_lat,
                    'shop_lng'         => $p->shop_lng,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Active broadcasts retrieved.',
            'data' => [
                'broadcasts' => $broadcasts,
                'float_meter'=> [
                    'current_float' => $collector->current_float_paise / 100,
                    'float_limit'   => $collector->float_limit_paise / 100,
                    'remaining_cap' => max(0, ($collector->float_limit_paise - $collector->current_float_paise) / 100),
                ],
            ],
            'errors' => [],
        ]);
    }

    /**
     * Accept Pickup (First-To-Accept Race Lock).
     */
    public function acceptJob(Request $request, int $pickupId): JsonResponse
    {
        $collector = $request->user()->collector;

        try {
            $assignment = app(PickupDispatchService::class)->acceptPickupWithRaceLock($pickupId, $collector->id);

            AuditLogService::log('PICKUP_ACCEPTED', 'PickupRequest', $pickupId, null, ['collector_id' => $collector->id], $request);

            return response()->json([
                'success' => true,
                'message' => 'Pickup accepted and locked to you successfully.',
                'data' => [
                    'assignment' => $assignment->load(['pickupRequest.retailer']),
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['race_lock' => $e->getMessage()],
            ], 409); // Conflict / Locked
        }
    }

    /**
     * Verify Server-Side 100m Geofence.
     */
    public function verifyGeofence(Request $request, int $pickupId): JsonResponse
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'is_mock'   => 'nullable|boolean',
            'timestamp' => 'nullable|integer',
        ]);

        $collector = $request->user()->collector;
        $pickup = PickupRequest::with(['retailer', 'assignment'])->findOrFail($pickupId);

        // IDOR Protection: Collector must be the assigned executive
        if (!$pickup->assignment || $pickup->assignment->collector_id !== $collector->id) {
            AuditLogService::logSecurityAlert(
                'IDOR_ATTEMPT',
                "Collector {$collector->collector_code} attempted unauthorized geofence check on Pickup #{$pickupId}.",
                ['pickup_id' => $pickupId]
            );

            return response()->json([
                'success' => false,
                'message' => 'Authorization Violation: You are not the assigned executive for this pickup.',
                'data' => null,
                'errors' => ['auth' => 'Unassigned pickup access denied.'],
            ], 403);
        }

        $geofenceService = app(GeofenceVerificationService::class);
        $result = $geofenceService->verifyGeofence(
            $collector,
            $pickup->retailer,
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (bool) $request->input('is_mock', false),
            $request->input('timestamp'),
            $request->input('accuracy_meters') ? (float) $request->input('accuracy_meters') : null
        );

        if (!$result['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => $result,
                'errors' => ['geofence' => $result['error']],
            ], 403);
        }

        // Update assignment geofence timestamp
        if ($pickup->assignment) {
            $pickup->assignment->update([
                'geofence_verified_at' => now(),
                'distance_at_arrival_m' => $result['distance_meters'],
                'status' => 'ARRIVED',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Geofence unlocked! You are within 100m of the shop.',
            'data' => $result,
            'errors' => [],
        ]);
    }

    /**
     * Submit Cash Collection & [ADD WALLET & APPROVE].
     */
    public function submitCash(Request $request, int $pickupId): JsonResponse
    {
        $request->validate([
            'collected_amount_rupees' => 'required|numeric|min:1',
            'denominations'           => 'required|array',
            'is_partial'              => 'nullable|boolean',
            'latitude'                => 'required|numeric',
            'longitude'               => 'required|numeric',
        ]);

        $collector = $request->user()->collector;
        $pickup = PickupRequest::with(['retailer', 'assignment'])->findOrFail($pickupId);

        if (!$pickup->assignment || $pickup->assignment->collector_id !== $collector->id) {
            return response()->json(['success' => false, 'message' => 'Assignment mismatch.'], 403);
        }

        // 1. Mandatory server-side 100m check
        $distance = GeofenceVerificationService::calculateDistanceMeters(
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (float) $pickup->shop_lat,
            (float) $pickup->shop_lng
        );

        if ($distance > 100.0) {
            return response()->json([
                'success' => false,
                'message' => "Collection blocked! You are {$distance}m from shop. Must be <= 100m.",
                'data' => null,
                'errors' => ['geofence' => 'Out of range.'],
            ], 403);
        }

        $collectedAmountPaise = (int) round($request->input('collected_amount_rupees') * 100);

        // 2. Validate note counts
        try {
            $denominationResult = app(CashDenominationService::class)->validateAndCalculate(
                $request->input('denominations'),
                $collectedAmountPaise
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['denominations' => $e->getMessage()],
            ], 422);
        }

        return DB::transaction(function () use ($pickup, $collector, $collectedAmountPaise, $denominationResult, $request) {
            $requestedAmountPaise = $pickup->requested_amount_paise;
            $shortfall = max(0, $requestedAmountPaise - $collectedAmountPaise);
            $isPartial = $shortfall > 0;

            $collectionCode = 'COLXN-' . date('Ymd') . '-' . Str::upper(Str::random(6));

            $collection = CashCollection::create([
                'collection_code'          => $collectionCode,
                'pickup_assignment_id'     => $pickup->assignment->id,
                'collector_id'             => $collector->id,
                'retailer_id'              => $pickup->retailer_id,
                'requested_amount_paise'   => $requestedAmountPaise,
                'collected_amount_paise'   => $collectedAmountPaise,
                'shortfall_amount_paise'   => $shortfall,
                'is_partial'               => $isPartial,
                'collector_approved_at'    => now(),
                'status'                   => 'PENDING_RETAILER_CONFIRMATION',
            ]);

            CashDenomination::create([
                'cash_collection_id'      => $collection->id,
                'count_500'               => $denominationResult['denominations']['count_500'],
                'count_200'               => $denominationResult['denominations']['count_200'],
                'count_100'               => $denominationResult['denominations']['count_100'],
                'count_50'                => $denominationResult['denominations']['count_50'],
                'count_20'                => $denominationResult['denominations']['count_20'],
                'count_10'                => $denominationResult['denominations']['count_10'],
                'count_coins'             => $denominationResult['denominations']['count_coins'],
                'total_notes'             => $denominationResult['total_notes'],
                'calculated_amount_paise' => $denominationResult['calculated_amount_paise'],
            ]);

            // Create thermal receipt record
            $receiptNumber = 'REC-' . date('Ymd') . '-' . Str::upper(Str::random(6));
            $receipt = Receipt::create([
                'receipt_number'        => $receiptNumber,
                'cash_collection_id'    => $collection->id,
                'qr_verification_token' => (string) Str::uuid(),
                'printed_at'            => now(),
            ]);

            $pickup->status = 'RETAILER_PENDING_CONFIRMATION';
            $pickup->save();

            $pickup->assignment->status = 'IN_COUNT';
            $pickup->assignment->save();

            AuditLogService::log('CASH_COLLECTED_SUBMITTED', 'CashCollection', $collection->id, null, [
                'collected_paise' => $collectedAmountPaise,
                'notes_count'     => $denominationResult['total_notes'],
            ], $request);

            return response()->json([
                'success' => true,
                'message' => 'Cash verified and approval dispatched. Waiting for Retailer to tap [ACCEPT & CONFIRM].',
                'data' => [
                    'collection' => $collection->load(['denominations', 'receipt']),
                    'total_notes'=> $denominationResult['total_notes'],
                ],
                'errors' => [],
            ]);
        });
    }

    /**
     * Get Collector Float Meter.
     */
    public function getFloatMeter(Request $request): JsonResponse
    {
        $collector = $request->user()->collector;

        return response()->json([
            'success' => true,
            'message' => 'Float meter retrieved.',
            'data' => [
                'current_float_rupees' => $collector->current_float_paise / 100,
                'float_limit_rupees'   => $collector->float_limit_paise / 100,
                'remaining_capacity'   => max(0, ($collector->float_limit_paise - $collector->current_float_paise) / 100),
                'percent_used'         => min(100, round(($collector->current_float_paise / $collector->float_limit_paise) * 100, 1)),
                'is_locked'            => $collector->current_float_paise >= $collector->float_limit_paise,
            ],
            'errors' => [],
        ]);
    }

    /**
     * Emergency Silent SOS Trigger.
     */
    public function triggerSos(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'        => 'required|numeric',
            'longitude'       => 'required|numeric',
            'battery_percent' => 'nullable|integer',
        ]);

        $collector = $request->user()->collector;

        $sos = SosAlert::create([
            'collector_id'      => $collector->id,
            'latitude'          => $request->input('latitude'),
            'longitude'         => $request->input('longitude'),
            'battery_percent'   => $request->input('battery_percent', 100),
            'cash_holding_paise'=> $collector->current_float_paise,
            'status'            => 'TRIGGERED',
        ]);

        $collector->update(['duty_status' => 'EMERGENCY']);

        AuditLogService::log('SOS_TRIGGERED', 'SosAlert', $sos->id, null, [
            'collector' => $collector->collector_code,
            'holding'   => $collector->current_float_paise,
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'EMERGENCY SOS ALERT SENT TO ADMIN AND SUPERVISORS.',
            'data' => ['sos' => $sos],
            'errors' => [],
        ]);
    }
}
