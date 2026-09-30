@extends('layouts.admin')

@section('title', 'Retailer Wallets & Financial Ledger')
@section('header_title', 'Retailer Wallets & Double-Entry Passbook')
@section('header_subtitle', 'Immutable ledger transactions, cash collection credits, recharge debits and BBPS utilities')

@section('content')
<div class="space-y-6">

    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Merchant Wallets Master</h3>
            <span class="text-xs font-semibold text-slate-500">Total Wallets: {{ $wallets->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Merchant Shop</th>
                        <th class="py-3.5 px-6">Owner & Mobile</th>
                        <th class="py-3.5 px-6">Spendable Balance</th>
                        <th class="py-3.5 px-6">Outstanding Udhar</th>
                        <th class="py-3.5 px-6">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($wallets as $w)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6 font-bold text-slate-900">
                                {{ $w->retailer->shop_name }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600 font-mono">
                                {{ $w->retailer->owner_name }} ({{ $w->retailer->user->mobile }})
                            </td>
                            <td class="py-4 px-6 font-extrabold text-indigo-600 text-base">
                                ₹{{ number_format($w->balance_paise / 100, 2) }}
                            </td>
                            <td class="py-4 px-6 font-bold {{ $w->retailer->outstanding_paise > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                ₹{{ number_format($w->retailer->outstanding_paise / 100, 2) }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500">
                                {{ $w->updated_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-400">No wallets found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $wallets->links() }}
        </div>
    </div>

</div>
@endsection
