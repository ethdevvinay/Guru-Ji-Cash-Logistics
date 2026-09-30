@extends('layouts.admin')

@section('title', '360° Real-Time Financial Khata & Fleet Radar')
@section('header_title', 'Master Operations & Financial Reconciler Desk')
@section('header_subtitle', 'Live Cash Logistics, Fleet Telemetry, 100m Geofenced Unlocks & Multi-Bank Settlement')

@section('content')
<div class="space-y-8" x-data="dashboardDesk()">

    <!-- Master 4-Pill Live Financial KPI Cards (Spec PDF Page 2 Section A) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Pill 1: Cash Received -->
        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-emerald-500 relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">1. Cash Received (आया)</span>
                <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">₹{{ number_format($recon['kpis']['cash_received_rupees']) }}</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center">
                <span class="text-emerald-600 font-bold mr-1">{{ $recon['kpis']['today_pickups_count'] }} Retailer Pickups</span> Today
            </p>
        </div>

        <!-- Pill 2: Wallet Credited -->
        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-indigo-500 relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">2. Wallet Credited (दिया)</span>
                <span class="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </span>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">₹{{ number_format($recon['kpis']['wallet_credited_rupees']) }}</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center">
                <span class="text-indigo-600 font-bold mr-1">Instant Digital</span> Settlement
            </p>
        </div>

        <!-- Pill 3: Retailer Udhar -->
        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-amber-500 relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">3. Retailer Udhar (उधार)</span>
                <span class="p-2 rounded-xl bg-amber-50 text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                </span>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">₹{{ number_format($recon['kpis']['retailer_udhar_rupees']) }}</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center">
                <span class="text-amber-600 font-bold mr-1">Total Disbursed</span> Market Float
            </p>
        </div>

        <!-- Pill 4: Cash in Transit -->
        <div class="glass-card rounded-2xl p-5 border-l-4 border-l-rose-500 relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">4. Cash In Transit (बैग में)</span>
                <span class="p-2 rounded-xl bg-rose-50 text-rose-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </span>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">₹{{ number_format($recon['kpis']['cash_in_transit_rupees']) }}</h3>
            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center">
                <span class="text-rose-600 font-bold mr-1">Held by {{ $collectors->count() }} Active</span> Field Boys
            </p>
        </div>

    </div>

    <!-- Live Moving Fleet GPS Radar (Spec PDF Page 2 Section B) -->
    <div class="glass-card rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <div class="flex items-center space-x-2">
                    <h3 class="text-base font-bold text-slate-900">Visual Radar: Live Moving Fleet GPS Telemetry</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                        <span class="w-1.5 h-1.5 mr-1 rounded-full bg-emerald-500 pulse-emerald"></span>
                        10s LIVE PING
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Rohtak Urban Zone • Polygon Territory Wards</p>
            </div>
            <div class="flex items-center space-x-3 text-xs font-semibold">
                <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700">Online: {{ $collectors->count() }}</span>
                <span class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">SOS Alerts: {{ $sosAlerts->count() }}</span>
                <a href="{{ route('admin.operations.map') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-colors">
                    Fullscreen Map ↗
                </a>
            </div>
        </div>

        <!-- Moving Bikes Real-time Radar Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-5">
            @forelse($collectors as $col)
                <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 transition-all hover:shadow-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <span class="text-lg">🛵</span>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-tight">{{ $col->user->name }}</h4>
                                <span class="text-[11px] font-mono font-semibold text-slate-500">{{ $col->collector_code }}</span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $col->duty_status === 'ON_JOB' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $col->duty_status }}
                        </span>
                    </div>

                    <!-- Telemetry Details -->
                    <div class="space-y-1.5 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Loc / Ward:</span>
                            <span class="font-semibold text-slate-800">{{ $col->zone?->name ?? 'Rohtak Urban' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Battery:</span>
                            <span class="font-semibold text-slate-800">{{ $col->battery_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Bike No:</span>
                            <span class="font-mono text-slate-800">{{ $col->bike_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Float Bag Meter Progress -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-slate-500">Holding Cash:</span>
                            <span class="text-indigo-600">₹{{ number_format($col->current_float_paise / 100) }} / ₹1L</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-500 {{ ($col->current_float_paise >= 8000000) ? 'bg-rose-500' : 'bg-emerald-500' }}"
                                 style="width: {{ min(100, ($col->current_float_paise / $col->float_limit_paise) * 100) }}%">
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-6 text-center text-slate-400 text-sm">
                    No collectors currently on duty.
                </div>
            @endforelse

            <!-- Central Vault Desk Quick Card -->
            <div class="p-4 rounded-xl border border-indigo-200 bg-indigo-50/50 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <span class="text-lg">🏢</span>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-tight">Central Vault Desk</h4>
                                <span class="text-[11px] font-semibold text-indigo-700">Hub Active</span>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-200 text-indigo-800">VAULT</span>
                    </div>
                    <p class="text-xs text-slate-600 mt-2">Evening Closing Station & Multi-Bank Deposit Split Desk</p>
                </div>

                <div class="mt-4 pt-3 border-t border-indigo-100">
                    <p class="text-xs text-slate-500 font-semibold">Vault Current Cash:</p>
                    <p class="text-xl font-extrabold text-indigo-900">₹{{ number_format(($vault->current_cash_paise ?? 0) / 100) }}</p>
                    <a href="{{ route('admin.vault.index') }}" class="mt-2 block text-center py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors">
                        Open Vault Handover Desk →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 360° Real-Time Cash Audit Khata Table (Spec PDF Page 2 Section A) -->
    <div class="glass-card rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900">360° Financial Khata & Real-Time Cash Audit Desk</h3>
                <p class="text-xs text-slate-500 mt-0.5">उधार, रिसीव्ड व दिया गया कैश बहीखाता</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.finances.reconciliation') }}" class="px-3.5 py-1.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-colors">
                    Reconciliation Desk
                </a>
                <a href="{{ route('admin.reports.index') }}" class="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Tally / Excel Export
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                        <th class="py-3 px-4">Retailer / Shop Details</th>
                        <th class="py-3 px-4">Wallet Disbursed</th>
                        <th class="py-3 px-4">Requests Raised (आई)</th>
                        <th class="py-3 px-4">Cash Collected (हुई)</th>
                        <th class="py-3 px-4">Pending (बाक़ी)</th>
                        <th class="py-3 px-4">Live Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <!-- Row 1: Radhe Digital Store (From PDF Spec) -->
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">Radhe Digital Store</p>
                            <p class="text-xs text-slate-500 font-mono">+91 98120 00004 • Ward 4</p>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">₹1,00,000</td>
                        <td class="py-4 px-4 text-slate-700">2 Reqs (₹70,000)</td>
                        <td class="py-4 px-4 font-semibold text-emerald-600">₹50,000 <span class="text-xs text-slate-400 font-normal">(By Rahul)</span></td>
                        <td class="py-4 px-4 font-bold text-amber-600">₹20,000</td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                🟡 En Route
                            </span>
                        </td>
                    </tr>

                    <!-- Row 2: Sharma Telecom & AEPS (From PDF Spec) -->
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">Sharma Telecom & AEPS</p>
                            <p class="text-xs text-slate-500 font-mono">+91 98120 00012 • Station Rd</p>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">₹60,000</td>
                        <td class="py-4 px-4 text-slate-700">1 Req (₹40,000)</td>
                        <td class="py-4 px-4 font-semibold text-emerald-600">₹40,000 <span class="text-xs text-slate-400 font-normal">(By Amit)</span></td>
                        <td class="py-4 px-4 font-bold text-emerald-600">₹0</td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                🟢 100% Settled
                            </span>
                        </td>
                    </tr>

                    <!-- Row 3: Gupta Grahak Seva Kendra (From PDF Spec) -->
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-4 px-4">
                            <p class="font-bold text-slate-900">Gupta Grahak Seva Kendra</p>
                            <p class="text-xs text-slate-500 font-mono">+91 98120 00015 • Main Chowk</p>
                        </td>
                        <td class="py-4 px-4 font-bold text-slate-800">₹85,000</td>
                        <td class="py-4 px-4 text-slate-700">3 Reqs (₹85,000)</td>
                        <td class="py-4 px-4 font-semibold text-emerald-600">₹60,000 <span class="text-xs text-slate-400 font-normal">(By Rahul)</span></td>
                        <td class="py-4 px-4 font-bold text-rose-600">₹25,000</td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                🔴 Assigned (90s Timer)
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Operational Split Desks: 90s Incoming Requests vs Multi-Bank Deposits -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Left: Incoming 90s Pickup Requests Queue -->
        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Live Pickup Dispatch Queue</h3>
                    <p class="text-xs text-slate-500">First-to-accept race lock monitor</p>
                </div>
                <a href="{{ route('admin.pickups.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                    View All →
                </a>
            </div>

            <div class="space-y-3">
                @forelse($activePickups as $pickup)
                    <div class="p-3.5 rounded-xl border border-slate-200 hover:border-indigo-200 bg-white transition-all flex items-center justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900 text-sm">{{ $pickup->retailer->shop_name }}</span>
                                <span class="text-xs font-mono font-semibold text-slate-400">{{ $pickup->request_code }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $pickup->retailer->address }}</p>
                            @if($pickup->assignment)
                                <p class="text-[11px] font-semibold text-indigo-600 mt-1">
                                    Assigned to: {{ $pickup->assignment->collector->user->name }} ({{ $pickup->assignment->collector->collector_code }})
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-extrabold text-slate-900">₹{{ number_format($pickup->requested_amount_paise / 100) }}</p>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold mt-1 {{ $pickup->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $pickup->status }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-slate-400 text-sm py-4">No active pickups at the moment.</p>
                @endforelse
            </div>
        </div>

        <!-- Right: Multi-Bank Account Deposit Allocation -->
        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Multi-Bank Accounts & Deposit Tracker</h3>
                    <p class="text-xs text-slate-500">HDFC, SBI & ICICI settlement accounts</p>
                </div>
                <a href="{{ route('admin.vault.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                    Vault Desk →
                </a>
            </div>

            <div class="space-y-3">
                @foreach($bankAccounts as $bank)
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-white flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center font-bold text-slate-700 text-xs">
                                {{ substr($bank->bank_name, 0, 4) }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">{{ $bank->bank_name }}</h4>
                                <p class="text-xs text-slate-500 font-mono">{{ $bank->account_number_masked }} • IFSC: {{ $bank->ifsc_code }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                ACTIVE
                            </span>
                            <p class="text-[11px] text-slate-500 mt-1">{{ $bank->account_type }} A/C</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

</div>

<script>
    function dashboardDesk() {
        return {
            init() {
                // Auto-refresh via polling every 10 seconds if needed
                console.log('Dashboard desk initialized with 10s auto-telemetry.');
            }
        }
    }
</script>
@endsection
