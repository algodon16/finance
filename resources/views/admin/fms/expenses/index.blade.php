@extends('layouts.admin')
@section('title', 'Expense and Disbursement Tracking')
@section('content')
<div class="page-header">
    <h2>Expense and Disbursement Tracking</h2>
    <a class="btn btn-primary" href="{{ route('admin.expenses.create') }}">Record Expense</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Approved Expenses</h4><p class="val">P{{ number_format($summary['total'], 2) }}</p></div>
    <div class="fms-stat"><h4>Pending Approval</h4><p class="val">P{{ number_format($summary['pending'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Paid</h4><p class="val">P{{ number_format($summary['paid'], 2) }}</p></div>
    <div class="fms-stat"><h4>Monthly Disbursement</h4><p class="val">P{{ number_format($summary['monthly'], 2) }}</p></div>
</div>

<div class="fms-two">
    <div class="fms-panel"><h3>Department Expenses</h3>
        @forelse($byDept as $d)<div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;"><span>{{ $d->department ?? 'Unassigned' }}</span><strong>P{{ number_format($d->t, 2) }}</strong></div>
        @empty<p style="color:#64748b;">No financial records available.</p>@endforelse</div>
    <div class="fms-panel"><h3>Expense Categories</h3>
        @forelse($byCat as $d)<div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;"><span>{{ $d->expense_category }}</span><strong>P{{ number_format($d->t, 2) }}</strong></div>
        @empty<p style="color:#64748b;">No financial records available.</p>@endforelse</div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search reference, payee, dept" value="{{ request('search') }}">
        <select name="approval_status" class="form-control" style="max-width:160px;"><option value="">All approvals</option>@foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" {{ request('approval_status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select>
        <select name="payment_status" class="form-control" style="max-width:150px;"><option value="">All payments</option>@foreach(['pending','paid'] as $s)<option value="{{ $s }}" {{ request('payment_status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select>
        <input type="date" name="date_from" class="form-control" style="max-width:170px;" value="{{ request('date_from') }}">
        <input type="date" name="date_to" class="form-control" style="max-width:170px;" value="{{ request('date_to') }}">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.expenses.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Reference</th><th>Category</th><th>Department</th><th>Payee</th><th style="text-align:right;">Amount</th><th>Date</th><th>Approval</th><th>Payment</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.expenses.show', $r) }}">{{ $r->reference_number }}</a></td>
                <td>{{ $r->expense_category }}</td><td>{{ $r->department ?? '—' }}</td><td>{{ $r->payee }}</td>
                <td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td><td>{{ $r->expense_date }}</td>
                <td><span class="status {{ $r->approval_status === 'approved' ? 'st-green' : ($r->approval_status === 'rejected' ? 'st-red' : 'st-amber') }}">{{ ucfirst($r->approval_status) }}</span></td>
                <td><span class="status {{ $r->payment_status === 'paid' ? 'st-green' : 'st-amber' }}">{{ ucfirst($r->payment_status) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.expenses.show', $r) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.expenses.edit', $r) }}">Edit</a></div></td>
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
