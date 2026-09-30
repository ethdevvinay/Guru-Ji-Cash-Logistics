@extends('layouts.admin')

@section('title', 'System & Operational Settings')
@section('header_title', 'Operational System Parameters')
@section('header_subtitle', 'Geofencing radius, collector cash float thresholds, cancellation penalty splits and countdowns')

@section('content')
<div class="space-y-6">

    <div class="glass-card rounded-2xl p-6 max-w-4xl">
        <div class="pb-4 border-b border-slate-100 mb-6">
            <h3 class="text-base font-bold text-slate-900">Configured Operational Thresholds</h3>
            <p class="text-xs text-slate-500 mt-0.5">Parameters governing real-world logistics, geofencing, and financial safety</p>
        </div>

        <div class="space-y-4">
            @foreach($settings as $setting)
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-mono font-bold text-indigo-700">{{ $setting->key }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $setting->description }}</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-sm font-extrabold text-slate-900 shadow-sm">
                            {{ $setting->value }}
                        </span>
                        <span class="text-xs font-semibold text-slate-400">
                            {{ $setting->type }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
