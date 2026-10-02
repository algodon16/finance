@extends('layouts.admin')
@section('title', 'Expense and Disbursement Tracking')
@section('content')
<div class="page-header">
    <h2>Expense and Disbursement Tracking</h2>
</div>
<p style="color:#64748b;font-size:0.85rem;">Approved APs appear here automatically as disbursements (ED-…). Totals count each transaction once.</p>

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
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div><label style="font-size:0.8rem;">Search</label><br><input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search reference, payee, dept" value="{{ request('search') }}"></div>
        <div><label style="font-size:0.8rem;">Approval</label><br>
        <select name="approval_status" class="form-control" style="min-width:160px;">
            <option value="">All approvals</option>
            @foreach(['draft','submitted','approved','rejected','revision','cancelled','pending'] as $s)
                <option value="{{ $s }}" {{ request('approval_status') === $s ? 'selected' : '' }}>{{ \App\Services\WorkflowService::label($s) }}</option>
            @endforeach
        </select></div>
        <div><label style="font-size:0.8rem;">Payment</label><br>
        <select name="payment_status" class="form-control" style="min-width:150px;">
            <option value="">All payments</option>
            @foreach(['pending' => 'Pending', 'for_disbursement' => 'For Disbursement', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid'] as $s => $label)
                <option value="{{ $s }}" {{ request('payment_status') === $s ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><label style="font-size:0.8rem;">Origin</label><br>
        <select name="origin" class="form-control" style="min-width:150px;">
            <option value="">All origins</option>
            <option value="auto" {{ request('origin') === 'auto' ? 'selected' : '' }}>Auto from AP</option>
            <option value="manual" {{ request('origin') === 'manual' ? 'selected' : '' }}>Manual</option>
        </select></div>
        <div><label style="font-size:0.8rem;">Date From</label><br><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div><label style="font-size:0.8rem;">Date To</label><br><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div><button class="btn btn-secondary" type="submit">Filter</button> <a class="btn btn-secondary" href="{{ route('admin.expenses.index') }}">Reset</a></div>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Expense ID</th><th>AP ID</th><th>Vendor</th><th>Description</th><th style="text-align:right;">Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.expenses.show', $r) }}">{{ $r->reference_number }}</a>@if($r->related_payable_id)<br><span class="status st-green">Auto</span>@endif</td>
                <td>@if($r->sourcePayable)<a href="{{ route('admin.payables.show', $r->sourcePayable) }}">{{ $r->sourcePayable->ap_number }}</a>@else<span style="color:#64748b;">—</span>@endif</td>
                <td>{{ $r->payee }}</td><td>{{ \Illuminate\Support\Str::limit($r->description, 45) }}</td>
                <td style="text-align:right;">P{{ number_format($r->amount, 2) }}</td><td>{{ $r->proposed_payment_date ?? $r->expense_date }}</td>
                <td><span class="status {{ $r->approval_status === 'approved' ? 'st-green' : ($r->approval_status === 'rejected' ? 'st-red' : 'st-amber') }}">{{ \App\Services\WorkflowService::label($r->approval_status) }}</span><br><span class="status {{ $r->payment_status === 'paid' ? 'st-green' : 'st-amber' }}">{{ \App\Services\WorkflowService::label($r->payment_status) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.expenses.show', $r) }}">View</a>@if(!$r->related_payable_id)<a class="btn btn-sm btn-secondary" href="{{ route('admin.expenses.edit', $r) }}">Edit</a>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="8" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
