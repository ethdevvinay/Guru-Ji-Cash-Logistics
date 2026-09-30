@extends('layouts.admin')

@section('title', '360-Degree Real-Time Financial Reconciler Desk')
@section('header_title', '360° Financial Audit & Reconciler Desk')
@section('header_subtitle', 'Zero-Discrepancy validation: Cash Received vs Wallet Credited vs In-Transit vs Central Vault vs Bank Deposits')

@section('content')
<div class="space-y-8">

    <!-- Reconciliation Status Alert Banner -->
    @if($recon['reconciliation']['is_balanced'])
        <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-3">
                <span class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">✔</span>
                <div>
                    <h3 class="text-sm font-bold text-emerald-900">360° MASTER RECONCILIATION: 100% BALANCED</h3>
                    <p class="text-xs text-emerald-700">Cash Received perfectly matches Digital Disbursal & Bank Deposit allocations. Zero note discrepancies.</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800">
                DELTA = ₹0.00
            </span>
        </div>
    @else
        <div class="p-5 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-3">
                <span class="w-8 h-8 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold text-sm">⚠</span>
                <div>
                    <h3 class="text-sm font-bold text-rose-900">RECONCILIATION DISCREPANCY FLAGGED</h3>
                    <p class="text-xs text-rose-700">Variance of ₹{{ number_format($recon['reconciliation']['discrepancy_rupees']) }} detected between physical collections and wallet credits.</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800">
                VARIANCE: ₹{{ number_format($recon['reconciliation']['discrepancy_rupees']) }}
            </span>
        </div>
    @endif

    <!-- 360° Financial Equation Breakdown Cards -->
    <div class="glass-card rounded-2xl p-6">
        <h3 class="text-base font-bold text-slate-900 pb-4 border-b border-slate-100 mb-6">
            Master 360-Degree Financial Balance Sheet
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-5 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">A. Total Physical Cash Received</span>
                <p class="text-2xl font-extrabold text-slate-900 mt-2">₹{{ number_format($recon['kpis']['cash_received_rupees']) }}</p>
                <p class="text-xs text-slate-500 mt-1">Verified note count from {{ $recon['kpis']['today_pickups_count'] }} pickups</p>
            </div>

            <div class="p-5 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">B. Total Digital Wallet Disbursed</span>
                <p class="text-2xl font-extrabold text-indigo-600 mt-2">₹{{ number_format($recon['kpis']['wallet_credited_rupees']) }}</p>
                <p class="text-xs text-slate-500 mt-1">Instant digital reload on retailer confirmation</p>
            </div>

            <div class="p-5 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">C. Physical Cash In Transit</span>
                <p class="text-2xl font-extrabold text-amber-600 mt-2">₹{{ number_format($recon['kpis']['cash_in_transit_rupees']) }}</p>
                <p class="text-xs text-slate-500 mt-1">Held in field collector bags</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="p-5 rounded-xl bg-indigo-50/50 border border-indigo-100">
                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider">D. Central Hub Vault Balance</span>
                <p class="text-2xl font-extrabold text-indigo-900 mt-2">₹{{ number_format($recon['kpis']['vault_balance_rupees']) }}</p>
                <p class="text-xs text-slate-500 mt-1">Verified physical cash waiting for bank deposit</p>
            </div>

            <div class="p-5 rounded-xl bg-emerald-50/50 border border-emerald-100">
                <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">E. Bank Deposits Cleared</span>
                <p class="text-2xl font-extrabold text-emerald-900 mt-2">₹{{ number_format($recon['kpis']['bank_deposits_rupees']) }}</p>
                <p class="text-xs text-slate-500 mt-1">HDFC + SBI allocations matched via UTR</p>
            </div>
        </div>
    </div>

</div>
@endsection
