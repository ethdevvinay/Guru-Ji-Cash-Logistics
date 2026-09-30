@extends('layouts.admin')

@section('title', 'Financial Reports & Data Export Center')
@section('header_title', 'Financial Reports & Data Export Center')
@section('header_subtitle', 'Tally, Excel, CSV and PDF downloads for day-end reconciliation & audits')

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Report 1: Daily Collection Report -->
        <div class="glass-card rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mb-4">
                    📊
                </div>
                <h3 class="text-base font-bold text-slate-900">Daily Cash Collection Report</h3>
                <p class="text-xs text-slate-500 mt-1">Date-wise tally of cash requested vs cash collected vs denominations.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center space-x-2">
                <button onclick="alert('Exporting Daily Cash Collection Report in Excel format...')" class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors text-center">
                    Download Excel
                </button>
                <button onclick="alert('Exporting CSV...')" class="py-2 px-3 border border-slate-300 hover:bg-slate-50 rounded-lg text-xs font-bold text-slate-700 transition-colors">
                    CSV
                </button>
            </div>
        </div>

        <!-- Report 2: Collector-wise Float & Performance -->
        <div class="glass-card rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold mb-4">
                    🛵
                </div>
                <h3 class="text-base font-bold text-slate-900">Collector Float & Duty Report</h3>
                <p class="text-xs text-slate-500 mt-1">Starting vs ending odometer, cash float holding, and evening vault handover status.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center space-x-2">
                <button onclick="alert('Exporting Collector Float Report...')" class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors text-center">
                    Download Excel
                </button>
                <button onclick="alert('Exporting CSV...')" class="py-2 px-3 border border-slate-300 hover:bg-slate-50 rounded-lg text-xs font-bold text-slate-700 transition-colors">
                    CSV
                </button>
            </div>
        </div>

        <!-- Report 3: Retailer Outstanding / Udhar Report -->
        <div class="glass-card rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold mb-4">
                    🏪
                </div>
                <h3 class="text-base font-bold text-slate-900">Retailer Udhar / Outstanding Ledger</h3>
                <p class="text-xs text-slate-500 mt-1">Outstanding market float balances, daily reminder logs, and collection history.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center space-x-2">
                <button onclick="alert('Exporting Retailer Outstanding Ledger...')" class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors text-center">
                    Download Excel
                </button>
                <button onclick="alert('Exporting CSV...')" class="py-2 px-3 border border-slate-300 hover:bg-slate-50 rounded-lg text-xs font-bold text-slate-700 transition-colors">
                    CSV
                </button>
            </div>
        </div>

        <!-- Report 4: 360° Master Reconciler Ledger -->
        <div class="glass-card rounded-2xl p-6 flex flex-col justify-between">
            <div>
                <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center font-bold mb-4">
                    📑
                </div>
                <h3 class="text-base font-bold text-slate-900">360° EOD Reconciliation Audit PDF</h3>
                <p class="text-xs text-slate-500 mt-1">Formal day-end closing certificate with multi-bank allocation and UTR references.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center space-x-2">
                <button onclick="alert('Generating Certified 360° EOD PDF Audit Sheet...')" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors text-center">
                    Generate PDF Report
                </button>
            </div>
        </div>

    </div>

</div>
@endsection
