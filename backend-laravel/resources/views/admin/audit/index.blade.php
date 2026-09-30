@extends('layouts.admin')

@section('title', 'Security & Financial Audit Logs')
@section('header_title', 'Immutable Security & Audit Ledger')
@section('header_subtitle', 'Complete tamper-proof audit trail of financial state transitions, logins, locks & cancellations')

@section('content')
<div class="space-y-6">

    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Audit Trail Records</h3>
            <span class="text-xs font-semibold text-slate-500">Total Events: {{ $logs->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Timestamp & IP</th>
                        <th class="py-3.5 px-6">Actor & Role</th>
                        <th class="py-3.5 px-6">Action</th>
                        <th class="py-3.5 px-6">Entity Target</th>
                        <th class="py-3.5 px-6">Changes / Audit State</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6 text-xs text-slate-500 font-mono">
                                <span class="font-bold text-slate-800">{{ $log->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i:s') }}</span><br>
                                <span class="text-slate-400">IP: {{ $log->ip_address ?? '127.0.0.1' }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-900 text-xs">{{ $log->actor?->name ?? 'System Process' }}</p>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                    {{ $log->actor_role }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-mono font-bold text-indigo-700 text-xs">
                                {{ $log->action }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-700">
                                <span class="font-semibold">{{ $log->entity_type }}</span> #{{ $log->entity_id }}
                            </td>
                            <td class="py-4 px-6 text-xs font-mono text-slate-600 max-w-xs truncate">
                                @if($log->after_state)
                                    <code>{{ json_encode($log->after_state) }}</code>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-400">No audit logs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection
