@extends('layouts.admin')

@section('title', 'Admin Flash Pop-up & Broadcast Engine')
@section('header_title', '1-Click Flash Pop-up & Broadcast Desk')
@section('header_subtitle', 'Live emergency modals, bank holidays, and cash timing announcements for merchant screens')

@section('content')
<div class="space-y-8" x-data="broadcastEngine()">

    <!-- Dispatch Flash Notice Form -->
    <div class="glass-card rounded-2xl p-6">
        <div class="pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-base font-bold text-slate-900">Broadcast New Flash Modal Announcement</h3>
            <p class="text-xs text-slate-500 mt-0.5">Dispatches an instant on-screen popup modal to active retailer mobile screens</p>
        </div>

        <form @submit.prevent="dispatchBroadcast" class="space-y-4 max-w-2xl">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Announcement Title</label>
                <input type="text" x-model="title" required placeholder="e.g. Bank Holiday Pickups till 8 PM"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-bold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Broadcast Message Content</label>
                <textarea x-model="message" required rows="3" placeholder="Enter detailed operational instruction for merchants..."
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 text-sm font-medium text-slate-900"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Priority</label>
                    <select x-model="priority" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm font-semibold text-slate-900 bg-white">
                        <option value="NORMAL">NORMAL</option>
                        <option value="URGENT">URGENT</option>
                        <option value="CRITICAL">CRITICAL (Emergency)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Target Audience</label>
                    <select x-model="targetRole" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm font-semibold text-slate-900 bg-white">
                        <option value="RETAILER">All Retailer Merchants</option>
                        <option value="COLLECTOR">All Field Collectors</option>
                        <option value="ALL">Everyone</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="py-3 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md shadow-indigo-600/20 transition-all">
                Dispatch Flash Announcement Now →
            </button>
        </form>
    </div>

    <!-- Active Broadcasts History Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Past Broadcast Dispatches</h3>
            <span class="text-xs font-semibold text-slate-500">Total: {{ $broadcasts->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Title & Message</th>
                        <th class="py-3.5 px-6">Priority</th>
                        <th class="py-3.5 px-6">Target</th>
                        <th class="py-3.5 px-6">Dispatched At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($broadcasts as $b)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-900">{{ $b->title }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ $b->message }}</p>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $b->priority === 'CRITICAL' ? 'bg-rose-100 text-rose-800' : ($b->priority === 'URGENT' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $b->priority }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-700 text-xs">
                                {{ $b->target_role }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500 font-mono">
                                {{ $b->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 text-slate-400">No broadcasts sent yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function broadcastEngine() {
        return {
            title: '',
            message: '',
            priority: 'NORMAL',
            targetRole: 'RETAILER',

            dispatchBroadcast() {
                fetch('/api/v1/admin/broadcasts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        title: this.title,
                        message: this.message,
                        priority: this.priority,
                        target_role: this.targetRole
                    })
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.message);
                        window.location.reload();
                    } else {
                        alert(json.message || 'Dispatch failed.');
                    }
                })
                .catch(() => alert('Network error during broadcast dispatch.'));
            }
        }
    }
</script>
@endsection
