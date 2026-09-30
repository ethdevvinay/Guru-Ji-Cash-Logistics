@extends('layouts.admin')

@section('title', 'Field Collectors Management')
@section('header_title', 'Field Cash Collectors Desk')
@section('header_subtitle', 'Device Binding, Live Cash Float Meter, Duty Status & Safety Caps')

@section('content')
<div class="space-y-6">

    <!-- Header Stats -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 shadow-sm">
                Total Collectors: {{ $collectors->total() }}
            </span>
            <span class="px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-700">
                Max Safety Cap: ₹1,00,000 / Collector
            </span>
        </div>
    </div>

    <!-- Collectors Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3.5 px-6">Collector Details</th>
                        <th class="py-3.5 px-6">Assigned Zone</th>
                        <th class="py-3.5 px-6">Duty & Telemetry</th>
                        <th class="py-3.5 px-6">Live Float Meter (₹1L Cap)</th>
                        <th class="py-3.5 px-6">Device Binding</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($collectors as $col)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-slate-700 text-sm">
                                        {{ substr($col->user->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 leading-tight">{{ $col->user->name }}</p>
                                        <p class="text-xs font-mono font-semibold text-slate-500">{{ $col->collector_code }} • {{ $col->user->mobile }}</p>
                                        <p class="text-[11px] text-slate-400">Bike: {{ $col->bike_number ?? 'Not assigned' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-700">
                                {{ $col->zone?->name ?? 'Unassigned' }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $col->duty_status === 'ON_JOB' ? 'bg-amber-50 text-amber-800 border border-amber-200' : ($col->duty_status === 'ON_DUTY' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $col->duty_status }}
                                </span>
                                <div class="text-[11px] text-slate-500 mt-1 flex items-center space-x-2">
                                    <span>Batt: {{ $col->battery_percent }}%</span>
                                    <span>•</span>
                                    <span>{{ $col->last_location_at ? $col->last_location_at->diffForHumans() : 'No ping' }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="w-48">
                                    <div class="flex justify-between text-xs font-bold mb-1">
                                        <span class="text-slate-700">₹{{ number_format($col->current_float_paise / 100) }}</span>
                                        <span class="text-slate-400">/ ₹{{ number_format($col->float_limit_paise / 100) }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 rounded-full {{ ($col->current_float_paise >= 8000000) ? 'bg-rose-500' : 'bg-emerald-500' }}"
                                             style="width: {{ min(100, ($col->current_float_paise / $col->float_limit_paise) * 100) }}%">
                                        </div>
                                    </div>
                                    @if($col->current_float_paise >= $col->float_limit_paise)
                                        <p class="text-[10px] font-bold text-rose-600 mt-1 uppercase tracking-wide">⚠ HARD CAP REACHED</p>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                @if($col->user->devices->first())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                                        ✔ Bound ({{ $col->user->devices->first()->device_model }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500">
                                        No Device Bound
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-right">
                                <a href="{{ route('admin.operations.map') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                    Radar Loc →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">No collectors found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $collectors->links() }}
        </div>
    </div>

</div>
@endsection
