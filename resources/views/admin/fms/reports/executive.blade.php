@extends('layouts.admin')
@section('title', 'Executive Financial Overview')
@section('content')
<div class="page-header"><h2>Executive Financial Overview</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Revenue</h4><p class="val">P{{ number_format($revenue, 2) }}</p></div>
    <div class="fms-stat"><h4>Total Expenses</h4><p class="val">P{{ number_format($expenses, 2) }}</p></div>
    <div class="fms-stat"><h4>Net Position</h4><p class="val">P{{ number_format($net, 2) }}</p></div>
    <div class="fms-stat"><h4>Available Funds</h4><p class="val">P{{ number_format($funds, 2) }}</p></div>
    <div class="fms-stat"><h4>Receivables</h4><p class="val">P{{ number_format($receivable, 2) }}</p></div>
    <div class="fms-stat"><h4>Payables</h4><p class="val">P{{ number_format($payables, 2) }}</p></div>
</div>
<div class="fms-panel">
    <h3>Cash Flow Summary (by Month)</h3>
    @forelse($monthly as $m)
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;"><span>{{ $m->m }}</span><strong>P{{ number_format($m->t, 2) }}</strong></div>
    @empty
        <p style="color:#64748b;">No financial records available.</p>
    @endforelse
</div>
@endsection
