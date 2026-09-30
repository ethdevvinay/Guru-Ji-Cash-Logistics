@extends('layouts.admin')

@section('title', 'Central Vault & Multi-Bank Allocation Desk')
@section('header_title', 'Central Vault & Multi-Bank Allocation Desk')
@section('header_subtitle', 'Evening cash closing, note counting verification, digital sign-off & multi-bank deposit split')

@section('content')
<div class="space-y-8" x-data="vaultClosingDesk()">

    <!-- Top Vault KPI Bar -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-indigo-600">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Central Vault Cash Balance</span>
            <h3 class="text-2xl font-extrabold text-slate-900 mt-1">₹{{ number_format(($vault->current_cash_paise ?? 0) / 100) }}</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium">Physical currency locked in hub vault</p>
        </div>

        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-amber-500">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Collectors With Cash In Bags</span>
            <h3 class="text-2xl font-extrabold text-amber-600 mt-1">{{ $collectorsWithCash->count() }} Field Officers</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium">Awaiting evening vault sign-off</p>
        </div>

        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-emerald-600">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Company Bank Accounts</span>
            <h3 class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $bankAccounts->count() }} Active Accounts</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium">HDFC, SBI & ICICI settlement accounts</p>
        </div>
    </div>

    <!-- Handover Desk & Note Machine Verification Desk -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Left: Collector Cash Handover & Float Reset Form -->
        <div class="glass-card rounded-2xl p-6">
            <div class="pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center space-x-2">
                    <span class="text-lg">🏢</span>
                    <h3 class="text-base font-bold text-slate-900">Evening Collector Cash Handover Desk</h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Count notes with cash counting machine, verify denominations & reset bag float to ₹0.00</p>
            </div>

            <form method="POST" action="/api/v1/admin/vault/handover" class="space-y-4" @submit.prevent="submitHandover">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select Collector</label>
                    <select name="collector_id" x-model="selectedCollectorId" required
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-semibold text-slate-900 bg-white">
                        <option value="">-- Choose Field Collector --</option>
                        @foreach($collectorsWithCash as $col)
                            <option value="{{ $col->id }}" data-float="{{ $col->current_float_paise / 100 }}">
                                {{ $col->user->name }} ({{ $col->collector_code }}) — Bag Float: ₹{{ number_format($col->current_float_paise / 100) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Handover Cash Amount (₹)</label>
                    <input type="number" name="amount_rupees" x-model="handoverAmount" required placeholder="e.g. 40000"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-extrabold text-slate-900">
                </div>

                <!-- Currency Note Counting Machine Breakdown Table -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Note Counting Machine Verification</p>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="text-slate-500">₹500 Notes:</label>
                            <input type="number" x-model.number="notes.count_500" placeholder="0" class="w-full px-2 py-1.5 rounded border border-slate-300 text-slate-900 font-bold">
                        </div>
                        <div>
                            <label class="text-slate-500">₹200 Notes:</label>
                            <input type="number" x-model.number="notes.count_200" placeholder="0" class="w-full px-2 py-1.5 rounded border border-slate-300 text-slate-900 font-bold">
                        </div>
                        <div>
                            <label class="text-slate-500">₹100 Notes:</label>
                            <input type="number" x-model.number="notes.count_100" placeholder="0" class="w-full px-2 py-1.5 rounded border border-slate-300 text-slate-900 font-bold">
                        </div>
                        <div>
                            <label class="text-slate-500">₹50 Notes:</label>
                            <input type="number" x-model.number="notes.count_50" placeholder="0" class="w-full px-2 py-1.5 rounded border border-slate-300 text-slate-900 font-bold">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center justify-center">
                    <span>Digital Vault Sign-Off & Reset Float to ₹0.00</span>
                </button>
            </form>
        </div>

        <!-- Right: Multi-Bank Account Deposit Allocation Desk -->
        <div class="glass-card rounded-2xl p-6">
            <div class="pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center space-x-2">
                    <span class="text-lg">🏦</span>
                    <h3 class="text-base font-bold text-slate-900">Multi-Bank Account Allocation</h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Split vault cash batches into company bank accounts (HDFC, SBI, ICICI) with UTR slip tracking</p>
            </div>

            <div class="space-y-4">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">HDFC Current Account</h4>
                            <p class="text-xs text-slate-500 font-mono">XXXX-XXXX-9012 • IFSC: HDFC0001248</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">Allocated: ₹3,00,000</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">UTR: <span class="font-mono font-bold text-slate-700">HDFCR52026093010482</span> • Matched</p>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">State Bank of India (SBI)</h4>
                            <p class="text-xs text-slate-500 font-mono">XXXX-XXXX-4589 • IFSC: SBIN0000624</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">Allocated: ₹1,85,000</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">UTR: <span class="font-mono font-bold text-slate-700">SBINR52026093081923</span> • Matched</p>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    function vaultClosingDesk() {
        return {
            selectedCollectorId: '',
            handoverAmount: '',
            notes: { count_500: 0, count_200: 0, count_100: 0, count_50: 0 },

            submitHandover() {
                fetch('/api/v1/admin/vault/handover', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        collector_id: this.selectedCollectorId,
                        amount_rupees: this.handoverAmount,
                        denominations: this.notes
                    })
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.message);
                        window.location.reload();
                    } else {
                        alert(json.message || 'Handover failed');
                    }
                })
                .catch(err => alert('Network error during handover'));
            }
        }
    }
</script>
@endsection
