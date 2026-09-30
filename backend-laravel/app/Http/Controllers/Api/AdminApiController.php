<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Collector;
use App\Models\Penalty;
use App\Models\PenaltyWaiver;
use App\Models\PickupRequest;
use App\Models\Retailer;
use App\Models\VaultBatch;
use App\Services\AuditLogService;
use App\Services\MasterReconciliationService;
use App\Services\VaultSettlementService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminApiController extends Controller
{
    /**
     * Get Master 4-Pill Live KPIs and Operations Desk counts.
     */
    public function getDashboardKpis(Request $request): JsonResponse
    {
        $recon = app(MasterReconciliationService::class)->calculateDaily360Reconciliation($request->input('date'));

        return response()->json([
            'success' => true,
            'message' => 'Dashboard KPIs retrieved.',
            'data' => $recon,
            'errors' => [],
        ]);
    }

    /**
     * Get Visual Fleet Radar (Active collectors with live 10s telemetry).
     */
    public function getLiveRadar(): JsonResponse
    {
        $collectors = Collector::with(['user', 'zone'])
            ->where('duty_status', '!=', 'OFF_DUTY')
            ->get()
            ->map(function ($c) {
                // Find currently assigned job if any
                $activeAssignment = $c->pickupAssignments()
                    ->whereIn('status', ['ASSIGNED', 'EN_ROUTE', 'ARRIVED', 'IN_COUNT'])
                    ->with('pickupRequest.retailer')
                    ->latest()
                    ->first();

                return [
                    'id'               => $c->id,
                    'collector_code'   => $c->collector_code,
                    'name'             => $c->user->name,
                    'mobile'           => $c->user->mobile,
                    'bike_number'      => $c->bike_number,
                    'zone'             => $c->zone?->name ?? 'General',
                    'current_lat'      => (float) $c->current_lat,
                    'current_lng'      => (float) $c->current_lng,
                    'battery_percent'  => $c->battery_percent,
                    'duty_status'      => $c->duty_status,
                    'current_float'    => $c->current_float_paise / 100,
                    'float_limit'      => $c->float_limit_paise / 100,
                    'float_display'    => '₹' . number_format($c->current_float_paise / 100) . ' / ₹' . number_format($c->float_limit_paise / 100),
                    'is_near_limit'    => ($c->current_float_paise / $c->float_limit_paise) >= 0.8,
                    'last_ping'        => $c->last_location_at,
                    'active_job'       => $activeAssignment ? [
                        'request_code'     => $activeAssignment->pickupRequest->request_code,
                        'shop_name'        => $activeAssignment->pickupRequest->retailer->shop_name,
                        'amount_rupees'    => $activeAssignment->pickupRequest->requested_amount_paise / 100,
                        'assignment_status'=> $activeAssignment->status,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Moving fleet radar telemetry retrieved.',
            'data' => [
                'collectors' => $collectors,
                'total_online' => $collectors->count(),
            ],
            'errors' => [],
        ]);
    }

    /**
     * 1-Click Penalty Waiver.
     */
    public function waivePenalty(Request $request, int $penaltyId): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);

        $penalty = Penalty::findOrFail($penaltyId);

        if ($penalty->status === 'WAIVED') {
            return response()->json(['success' => false, 'message' => 'Penalty is already waived.'], 422);
        }

        PenaltyWaiver::create([
            'penalty_id'     => $penalty->id,
            'admin_user_id'  => $request->user()->id,
            'waiver_reason'  => $request->input('reason'),
            'approved_at'    => now(),
        ]);

        $penalty->update(['status' => 'WAIVED']);

        AuditLogService::log('PENALTY_WAIVED', 'Penalty', $penalty->id, null, [
            'reason' => $request->input('reason'),
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Penalty waived and marked in audit ledger.',
            'data' => ['penalty' => $penalty],
            'errors' => [],
        ]);
    }

    /**
     * Settle Evening Vault Handover & Reset Collector Float to 0.
     */
    public function settleVaultHandover(Request $request): JsonResponse
    {
        $request->validate([
            'collector_id'   => 'required|exists:collectors,id',
            'amount_rupees'  => 'required|numeric|min:1',
            'denominations'  => 'nullable|array',
            'batch_code'     => 'nullable|string',
        ]);

        $admin = $request->user();
        $amountPaise = (int) round($request->input('amount_rupees') * 100);

        // Find or create today's open vault batch
        $batch = VaultBatch::firstOrCreate(
            ['status' => 'OPEN'],
            [
                'batch_code'               => 'VB-' . date('Ymd') . '-' . Str::upper(Str::random(4)),
                'admin_user_id'            => $admin->id,
                'total_cash_counted_paise' => 0,
                'total_collectors_settled' => 0,
            ]
        );

        try {
            $vaultTxn = app(VaultSettlementService::class)->settleEveningCollectorHandover(
                $batch->id,
                (int) $request->input('collector_id'),
                $amountPaise,
                $request->input('denominations', []),
                $admin->id
            );

            AuditLogService::log('VAULT_FLOAT_RESET', 'VaultTransaction', $vaultTxn->id, null, [
                'collector_id' => $request->input('collector_id'),
                'amount_paise' => $amountPaise,
            ], $request);

            return response()->json([
                'success' => true,
                'message' => "Vault handover successful. Collector float reset to ₹0.00.",
                'data' => [
                    'vault_transaction' => $vaultTxn,
                    'batch'             => $batch->fresh(),
                ],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['vault' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Allocate Vault Cash to Multi-Bank Accounts.
     */
    public function allocateBankDeposit(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id'    => 'required|exists:vault_batches,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.bank_account_id' => 'required|exists:bank_accounts,id',
            'allocations.*.amount_rupees'   => 'required|numeric|min:1',
            'allocations.*.utr_number'      => 'nullable|string',
        ]);

        $admin = $request->user();

        $sanitizedAllocations = array_map(function ($a) {
            return [
                'bank_account_id' => $a['bank_account_id'],
                'amount_paise'    => (int) round($a['amount_rupees'] * 100),
                'utr_number'      => $a['utr_number'] ?? null,
                'deposit_date'    => now()->toDateString(),
            ];
        }, $request->input('allocations'));

        try {
            $deposits = app(VaultSettlementService::class)->allocateBankDeposits(
                (int) $request->input('batch_id'),
                $sanitizedAllocations,
                $admin->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Vault cash allocated to bank accounts successfully.',
                'data' => ['deposits' => $deposits],
                'errors' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['allocation' => $e->getMessage()],
            ], 422);
        }
    }

    /**
     * Create 1-Click Flash Broadcast Modal Notice.
     */
    public function createBroadcast(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:150',
            'message'     => 'required|string',
            'priority'    => 'required|in:NORMAL,URGENT,CRITICAL',
            'target_role' => 'required|in:ALL,RETAILER,COLLECTOR',
            'expires_at'  => 'nullable|date',
        ]);

        $broadcast = Broadcast::create([
            'admin_user_id'  => $request->user()->id,
            'title'          => $request->input('title'),
            'message'        => $request->input('message'),
            'priority'       => $request->input('priority'),
            'target_role'    => $request->input('target_role'),
            'is_flash_modal' => true,
            'starts_at'      => now(),
            'expires_at'     => $request->input('expires_at') ? now()->parse($request->input('expires_at')) : now()->addHours(24),
        ]);

        AuditLogService::log('BROADCAST_CREATED', 'Broadcast', $broadcast->id, null, [
            'title' => $broadcast->title,
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Broadcast dispatched live to all targeted devices.',
            'data' => ['broadcast' => $broadcast],
            'errors' => [],
        ]);
    }
}
