@extends('layouts.admin')

@section('title', 'Retailer Merchants Management')
@section('header_title', 'Retailer Merchants Desk')
@section('header_subtitle', 'Shops, GPS Geofence Locations, Instant Wallet Reloads & Market Float')

@section('content')
<div class="space-y-6">

    <!-- Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 shadow-sm">
                Total Merchants: {{ $retailers->total() }}
            </span>
            <span class="px-3.5 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-xs font-bold text-amber-700">
                100m Hardware Geofence Enforced
            </span>
        </div>
    </div>

    <!-- Retailers Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Shop & Merchant Details</th>
                        <th class="py-3.5 px-6">Zone & Address</th>
                        <th class="py-3.5 px-6">Wallet Balance</th>
                        <th class="py-3.5 px-6">Outstanding Udhar</th>
                        <th class="py-3.5 px-6">GPS Coordinates</th>
                        <th class="py-3.5 px-6">KYC Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($retailers as $ret)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-900 leading-tight">{{ $ret->shop_name }}</p>
                                <p class="text-xs text-slate-600 font-semibold">{{ $ret->owner_name }}</p>
                                <p class="text-xs font-mono text-slate-400">{{ $ret->user->mobile }}</p>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                                    {{ $ret->zone?->name ?? 'Unassigned' }}
                                </span>
                                <p class="text-xs text-slate-500 mt-1 max-w-[200px] truncate" title="{{ $ret->address }}">{{ $ret->address }}</p>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-extrabold text-slate-900">₹{{ number_format(($ret->wallet->balance_paise ?? 0) / 100) }}</p>
                                <span class="text-[11px] text-emerald-600 font-bold">● Active Spendable</span>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-extrabold {{ $ret->outstanding_paise > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                    ₹{{ number_format($ret->outstanding_paise / 100) }}
                                </p>
                                @if($ret->outstanding_paise > 0)
                                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                        Pending Alert Sent
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                                        Cleared
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 font-mono text-xs text-slate-600">
                                <span class="text-slate-400">Lat:</span> {{ number_format($ret->latitude, 4) }}<br>
                                <span class="text-slate-400">Lng:</span> {{ number_format($ret->longitude, 4) }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $ret->is_kyc_verified ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800' }}">
                                    {{ $ret->is_kyc_verified ? '✔ KYC Verified' : 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">No retailers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $retailers->links() }}
        </div>
    </div>

</div>
@endsection
