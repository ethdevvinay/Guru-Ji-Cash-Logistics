<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Broadcast;
use App\Models\CashCollection;
use App\Models\Collector;
use App\Models\Penalty;
use App\Models\PickupRequest;
use App\Models\Retailer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SosAlert;
use App\Models\Territory;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultBatch;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Zone;
use App\Services\MasterReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPanelController extends Controller
{
    /**
     * Master 360° Operations Dashboard.
     */
    public function dashboard(Request $request): View
    {
        $recon = app(MasterReconciliationService::class)->calculateDaily360Reconciliation($request->input('date'));

        $activePickups = PickupRequest::with(['retailer', 'assignment.collector'])
            ->latest()
            ->take(8)
            ->get();

        $collectors = Collector::with(['user', 'zone'])
            ->where('duty_status', '!=', 'OFF_DUTY')
            ->get();

        $recentCollections = CashCollection::with(['retailer', 'collector.user', 'denominations'])
            ->latest()
            ->take(6)
            ->get();

        $sosAlerts = SosAlert::with(['collector.user'])
            ->where('status', 'TRIGGERED')
            ->latest()
            ->get();

        $vault = Vault::first();
        $bankAccounts = BankAccount::where('is_active', true)->get();

        return view('admin.dashboard', compact(
            'recon',
            'activePickups',
            'collectors',
            'recentCollections',
            'sosAlerts',
            'vault',
            'bankAccounts'
        ));
    }

    /**
     * Collectors Fleet Operations Desk.
     */
    public function collectors(): View
    {
        $collectors = Collector::with(['user', 'zone', 'dutySessions' => function ($q) {
            $q->latest()->take(1);
        }])->latest()->paginate(15);

        $zones = Zone::all();

        return view('admin.collectors.index', compact('collectors', 'zones'));
    }

    /**
     * Retailers Merchant Operations Desk.
     */
    public function retailers(): View
    {
        $retailers = Retailer::with(['user', 'zone', 'wallet'])
            ->latest()
            ->paginate(15);

        $zones = Zone::all();

        return view('admin.retailers.index', compact('retailers', 'zones'));
    }

    /**
     * Pickup Requests & 90s Race Monitor Desk.
     */
    public function pickups(): View
    {
        $pickups = PickupRequest::with(['retailer', 'zone', 'assignment.collector.user'])
            ->latest()
            ->paginate(20);

        return view('admin.pickups.index', compact('pickups'));
    }

    /**
     * Visual Live Moving Fleet GPS Map Radar.
     */
    public function liveMap(): View
    {
        $collectors = Collector::with(['user', 'zone'])
            ->where('duty_status', '!=', 'OFF_DUTY')
            ->get();

        $retailers = Retailer::all();

        return view('admin.operations.map', compact('collectors', 'retailers'));
    }

    /**
     * Territories & Zones Desk.
     */
    public function territories(): View
    {
        $territories = Territory::with('zones.collectors')->get();

        return view('admin.territories.index', compact('territories'));
    }

    /**
     * Retailer Wallets & Passbook Desk.
     */
    public function wallets(): View
    {
        $wallets = Wallet::with('retailer.user')->latest()->paginate(20);
        $recentTransactions = WalletTransaction::with('retailer.user')->latest()->take(15)->get();

        return view('admin.finances.wallets', compact('wallets', 'recentTransactions'));
    }

    /**
     * Evening Vault Handover & Multi-Bank Desk.
     */
    public function vault(): View
    {
        $vault = Vault::first();
        $batches = VaultBatch::with(['admin', 'transactions.collector.user', 'deposits.bankAccount'])
            ->latest()
            ->paginate(10);

        $collectorsWithCash = Collector::with('user')
            ->where('current_float_paise', '>', 0)
            ->get();

        $bankAccounts = BankAccount::where('is_active', true)->get();

        return view('admin.vault.index', compact('vault', 'batches', 'collectorsWithCash', 'bankAccounts'));
    }

    /**
     * 360-Degree Real-time Reconciler Desk.
     */
    public function reconciliation(Request $request): View
    {
        $recon = app(MasterReconciliationService::class)->calculateDaily360Reconciliation($request->input('date'));
        $bankDeposits = BankDeposit::with('bankAccount')->latest()->paginate(15);

        return view('admin.finances.reconciliation', compact('recon', 'bankDeposits'));
    }

    /**
     * Penalties & Waivers Desk.
     */
    public function penalties(): View
    {
        $penalties = Penalty::with(['retailer.user', 'pickupRequest', 'waiver.admin'])
            ->latest()
            ->paginate(20);

        return view('admin.finances.penalties', compact('penalties'));
    }

    /**
     * Flash Modals & Broadcasts Desk.
     */
    public function broadcasts(): View
    {
        $broadcasts = Broadcast::with('admin')->latest()->paginate(15);

        return view('admin.communications.broadcasts', compact('broadcasts'));
    }

    /**
     * Reports & Exports Center.
     */
    public function reports(): View
    {
        return view('admin.reports.index');
    }

    /**
     * Security & Audit Logs.
     */
    public function auditLogs(): View
    {
        $logs = AuditLog::with('actor')->latest()->paginate(25);

        return view('admin.audit.index', compact('logs'));
    }

    /**
     * Operational Settings.
     */
    public function settings(): View
    {
        $settings = Setting::all();

        return view('admin.settings.index', compact('settings'));
    }
}
