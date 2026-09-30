@extends('layouts.admin')

@section('title', 'Penalties & 1-Click Waiver Desk')
@section('header_title', 'Cancellation Penalties & Waiver Desk')
@section('header_subtitle', 'Retailer cancellation fee (₹50 = ₹35 Collector Petrol + ₹15 Admin) and 1-click waiver')

@section('content')
<div class="space-y-6">

    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Cancellation Penalties Log</h3>
            <span class="text-xs font-semibold text-slate-500">Total Penalties: {{ $penalties->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Retailer Shop</th>
                        <th class="py-3.5 px-6">Penalty Amount</th>
                        <th class="py-3.5 px-6">Split Allocation</th>
                        <th class="py-3.5 px-6">Reason</th>
                        <th class="py-3.5 px-6">Status</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($penalties as $pen)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-900">{{ $pen->retailer->shop_name }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $pen->retailer->user->mobile }}</p>
                            </td>
                            <td class="py-4 px-6 font-extrabold text-rose-600 text-base">
                                ₹{{ number_format($pen->amount_paise / 100) }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600">
                                <span>Collector: ₹{{ number_format($pen->collector_share_paise / 100) }}</span><br>
                                <span>Admin: ₹{{ number_format($pen->admin_share_paise / 100) }}</span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600">
                                {{ $pen->reason }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold {{ $pen->status === 'WAIVED' ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                    {{ $pen->status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                @if($pen->status !== 'WAIVED')
                                    <button onclick="waivePenalty({{ $pen->id }})" class="px-3 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-xs font-bold border border-amber-200 transition-colors">
                                        1-Click Waive
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400 font-semibold">Waived</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">No penalties recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $penalties->links() }}
        </div>
    </div>

</div>

<script>
    function waivePenalty(id) {
        const reason = prompt('Enter waiver reason (e.g. genuine merchant emergency):');
        if (!reason) return;

        fetch(`/api/v1/admin/penalties/${id}/waive`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reason })
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.message);
                window.location.reload();
            } else {
                alert(json.message || 'Waiver failed.');
            }
        })
        .catch(() => alert('Network error during penalty waiver.'));
    }
</script>
@endsection
