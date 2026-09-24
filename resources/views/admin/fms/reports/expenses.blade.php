@extends('layouts.admin')
@section('title', 'Expense Report')
@section('content')
<div class="page-header"><h2>Expense Report ({{ $from }} to {{ $to }})</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="date" name="date_from" class="form-control" style="max-width:180px;" value="{{ $from }}">
        <input type="date" name="date_to" class="form-control" style="max-width:180px;" value="{{ $to }}">
        <input type="text" name="department" class="form-control" style="max-width:200px;" placeholder="Department" value="{{ request('department') }}">
        <input type="text" name="expense_category" class="form-control" style="max-width:200px;" placeholder="Category" value="{{ request('expense_category') }}">
        <button class="btn btn-secondary" type="submit">Generate</button>
    </form>
</div>
<div class="fms-panel">
    <p><strong>Total Approved Expenses:</strong> P{{ number_format($total, 2) }}</p>
    <table class="fms-table"><thead><tr><th>Date</th><th>Reference</th><th>Category</th><th>Department</th><th>Payee</th><th style="text-align:right;">Amount</th><th>Approval</th></tr></thead><tbody>
    @forelse($records as $r)<tr><td>{{ $r->expense_date }}</td><td>{{ $r->reference_number }}</td><td>{{ $r->expense_category }}</td><td>{{ $r->department ?? '—' }}</td><td>{{ $r->payee }}</td><td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td><td>{{ ucfirst($r->approval_status) }}</td></tr>
    @empty<tr><td colspan="7" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
