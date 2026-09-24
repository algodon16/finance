@extends('layouts.admin')
@section('title', 'Accounts Payable Management')
@section('content')
<div class="page-header">
    <h2>Accounts Payable Management</h2>
    <a class="btn btn-primary" href="{{ route('admin.payables.create') }}">Record Payable</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Payable</h4><p class="val">P{{ number_format($summary['total'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Paid</h4><p class="val">P{{ number_format($summary['paid'], 2) }}</p></div>
    <div class="fms-stat"><h4>Remaining Balance</h4><p class="val">P{{ number_format($summary['remaining'], 2) }}</p></div>
    <div class="fms-stat"><h4>Overdue / Due Soon</h4><p class="val">P{{ number_format($summary['overdue'], 2) }} / P{{ number_format($summary['due_soon'], 2) }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search vendor or invoice" value="{{ request('search') }}">
        <select name="payment_status" class="form-control" style="max-width:180px;"><option value="">All statuses</option>@foreach(['pending','due_soon','overdue','partially_paid','fully_paid'] as $s)<option value="{{ $s }}" {{ request('payment_status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.payables.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Vendor</th><th>Invoice No.</th><th>Invoice Date</th><th>Due Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Remaining</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td>{{ $r->vendor }}</td>
                <td><a href="{{ route('admin.payables.show', $r) }}">{{ $r->invoice_number }}</a></td>
                <td>{{ $r->invoice_date }}</td><td>{{ $r->due_date }}</td>
                <td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->amount_paid, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->remaining_balance, 2) }}</td>
                <td><span class="status {{ $r->derived_status === 'Fully Paid' ? 'st-green' : ($r->derived_status === 'Overdue' ? 'st-red' : 'st-amber') }}">{{ $r->derived_status }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.payables.show', $r) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.payables.edit', $r) }}">Edit</a></div></td>
            </tr>
        @empty
            <tr><td colspan="9" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
