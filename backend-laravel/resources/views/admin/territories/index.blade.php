@extends('layouts.admin')

@section('title', 'Territories & Zones Management')
@section('header_title', 'Territories & Polygon Delivery Zones')
@section('header_subtitle', 'Urban & rural zone divisions, collector assignments and pickup radiuses')

@section('content')
<div class="space-y-6">

    @foreach($territories as $territory)
        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ $territory->name }} ({{ $territory->code }})</h3>
                    <p class="text-xs text-slate-500 font-medium">State: {{ $territory->state }} • Zones: {{ $territory->zones->count() }}</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    ACTIVE TERRITORY
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($territory->zones as $zone)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-bold text-slate-900">{{ $zone->name }}</h4>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-200 text-slate-700 font-bold">{{ $zone->code }}</span>
                        </div>
                        <p class="text-xs text-slate-500">Collectors Assigned: <span class="font-bold text-slate-800">{{ $zone->collectors->count() }}</span></p>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

</div>
@endsection
