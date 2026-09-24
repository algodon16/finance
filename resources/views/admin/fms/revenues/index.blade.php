@extends('layouts.admin')
@section('title', 'Revenue Management')
@section('content')
<div class="page-header">
    <h2>Revenue Management</h2>
    <div class="no-print" style="display:flex;gap:8px;">
        <a class="btn btn-success" href="{{ route('admin.revenues.export', request()->query()) }}">Export CSV</a>
        <a class="btn btn-primary" href="{{ route('admin.revenues.create') }}">Record Incoming Payment</a>
    </div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Revenue</h4><p class="val">P{{ number_format($summary['total'], 2) }}</p></div>
    <div class="fms-stat"><h4>Daily Revenue</h4><p class="val">P{{ number_format($summary['daily'], 2) }}</p></div>
    <div class="fms-stat"><h4>Monthly Revenue</h4><p class="val">P{{ number_format($summary['monthly'], 2) }}</p></div>
    <div class="fms-stat"><h4>Annual Revenue</h4><p class="val">P{{ number_format($summary['annual'], 2) }}</p></div>
</div>

<div class="fms-two">
    <div class="fms-panel"><h3>Revenue by Category</h3>
        @forelse($byCategory as $c)<div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;"><span>{{ $c->fee_category ?? 'Uncategorized' }}</span><strong>P{{ number_format($c->t, 2) }}</strong></div>
        @empty<p style="color:#64748b;">No financial records available.</p>@endforelse
    </div>
    <div class="fms-panel"><h3>Payment Breakdown by Method</h3>
        @forelse($byMethod as $c)<div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;"><span>{{ $c->payment_method ?? '—' }}</span><strong>P{{ number_format($c->t, 2) }}</strong></div>
        @empty<p style="color:#64748b;">No financial records available.</p>@endforelse
    </div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search student, TRX, reference" value="{{ request('search') }}">
        <select name="status" class="form-control" style="max-width:150px;"><option value="">All status</option>@foreach(['pending','approved','verified','rejected'] as $s)<option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select>
        <select name="verification_status" class="form-control" style="max-width:170px;"><option value="">All verification</option>@foreach(['pending','verified','rejected','reconciled'] as $s)<option value="{{ $s }}" {{ request('verification_status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select>
        <input type="date" name="date_from" class="form-control" style="max-width:170px;" value="{{ request('date_from') }}">
        <input type="date" name="date_to" class="form-control" style="max-width:170px;" value="{{ request('date_to') }}">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.revenues.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Transaction No.</th><th>Student</th><th>Date</th><th>Method</th><th>Category</th><th style="text-align:right;">Amount</th><th>Status</th><th>Verification</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.revenues.show', $r) }}">{{ $r->transaction_number ?? ('PAY-'.$r->id) }}</a></td>
                <td>{{ $r->student->full_name ?? '—' }}<br><small style="color:#64748b;">{{ $r->student->student_number ?? '' }}</small></td>
                <td>{{ $r->payment_date }}</td>
                <td>{{ $r->payment_method }}</td>
                <td>{{ $r->fee_category ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td>
                <td><span class="status {{ in_array($r->status, ['approved','verified']) ? 'st-green' : ($r->status === 'rejected' ? 'st-red' : 'st-amber') }}">{{ ucfirst($r->status) }}</span></td>
                <td><span class="status {{ $r->verification_status === 'reconciled' ? 'st-green' : ($r->verification_status === 'rejected' ? 'st-red' : 'st-amber') }}">{{ ucfirst($r->verification_status) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.revenues.show', $r) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.revenues.edit', $r) }}">Edit</a></div></td>
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
