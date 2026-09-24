@extends('layouts.admin')
@section('title', 'Revenue Report')
@section('content')
<div class="page-header"><h2>Revenue Report ({{ $from }} to {{ $to }})</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="date" name="date_from" class="form-control" style="max-width:180px;" value="{{ $from }}">
        <input type="date" name="date_to" class="form-control" style="max-width:180px;" value="{{ $to }}">
        <input type="text" name="fee_category" class="form-control" style="max-width:200px;" placeholder="Fee category" value="{{ request('fee_category') }}">
        <input type="text" name="payment_method" class="form-control" style="max-width:200px;" placeholder="Payment method" value="{{ request('payment_method') }}">
        <button class="btn btn-secondary" type="submit">Generate</button>
    </form>
</div>
<div class="fms-panel">
    <p><strong>Total Revenue:</strong> P{{ number_format($total, 2) }}</p>
    <table class="fms-table"><thead><tr><th>Date</th><th>Transaction</th><th>Student</th><th>Method</th><th>Category</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
    @forelse($records as $r)<tr><td>{{ $r->payment_date }}</td><td>{{ $r->transaction_number ?? ('PAY-'.$r->id) }}</td><td>{{ $r->student->full_name ?? '—' }}</td><td>{{ $r->payment_method }}</td><td>{{ $r->fee_category ?? '—' }}</td><td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td><td>{{ ucfirst($r->status) }}</td></tr>
    @empty<tr><td colspan="7" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
