@extends('layouts.admin')

@section('title', '90s Pickup Requests & Race Lock Monitor')
@section('header_title', 'Pickup Requests & Auto-Dispatch Desk')
@section('header_subtitle', '90-Second Territory Broadcasts, Single-Winner Locks & Cash Collections')

@section('content')
<div class="space-y-6">

    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">All Incoming & Active Requests</h3>
            <span class="text-xs font-semibold text-slate-500">Total: {{ $pickups->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Request Code</th>
                        <th class="py-3.5 px-6">Retailer Shop</th>
                        <th class="py-3.5 px-6">Amount Requested</th>
                        <th class="py-3.5 px-6">Assigned Collector</th>
                        <th class="py-3.5 px-6">Current Status</th>
                        <th class="py-3.5 px-6">Broadcast / Timeline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pickups as $p)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6 font-mono font-bold text-slate-900 text-xs">
                                {{ $p->request_code }}
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-900">{{ $p->retailer->shop_name }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $p->retailer->user->mobile }} • {{ $p->zone->name }}</p>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-extrabold text-slate-900 text-base">₹{{ number_format($p->requested_amount_paise / 100) }}</p>
                            </td>
                            <td class="py-4 px-6">
                                @if($p->assignment)
                                    <div class="flex items-center space-x-2">
                                        <span class="text-base">🛵</span>
                                        <div>
                                            <p class="font-bold text-slate-900 text-xs">{{ $p->assignment->collector->user->name }}</p>
                                            <span class="font-mono text-[11px] text-slate-500 font-semibold">{{ $p->assignment->collector->collector_code }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500">
                                        Pending Acceptance
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold 
                                    {{ $p->status === 'COMPLETED' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 
                                       ($p->status === 'BROADCASTING' ? 'bg-indigo-50 text-indigo-800 border border-indigo-200 pulse-emerald' : 
                                       ($p->status === 'ACCEPTED' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ $p->status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500">
                                <span>{{ $p->created_at->setTimezone('Asia/Kolkata')->format('d M, h:i A') }}</span>
                                @if($p->broadcast_expires_at && $p->status === 'BROADCASTING')
                                    <p class="text-indigo-600 font-bold mt-0.5">
                                        {{ max(0, now()->diffInSeconds($p->broadcast_expires_at, false)) }}s countdown
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">No pickup requests recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $pickups->links() }}
        </div>
    </div>

</div>
@endsection
