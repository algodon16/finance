@extends('layouts.admin')
@section('title', 'Payables Aging Report')
@section('content')
<div class="page-header"><h2>Accounts Payable Aging Report</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Current</h4><p class="val">P{{ number_format($aging['current'], 2) }}</p></div>
    <div class="fms-stat"><h4>Due Soon (7 days)</h4><p class="val">P{{ number_format($aging['due_soon'], 2) }}</p></div>
    <div class="fms-stat"><h4>Overdue</h4><p class="val">P{{ number_format($aging['overdue'], 2) }}</p></div>
</div>
<div class="fms-panel">
    <table class="fms-table"><thead><tr><th>Vendor</th><th>Invoice</th><th>Due Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Remaining</th><th>Aging</th></tr></thead><tbody>
    @forelse($records as $r)<tr><td>{{ $r->vendor }}</td><td>{{ $r->invoice_number }}</td><td>{{ $r->due_date }}</td><td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td><td style="text-align:right;">P{{ number_format($r->amount_paid, 2) }}</td><td style="text-align:right;">P{{ number_format($r->remaining_balance, 2) }}</td><td>{{ $r->derived_status }}</td></tr>
    @empty<tr><td colspan="7" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
