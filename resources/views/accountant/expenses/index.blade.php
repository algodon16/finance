@extends('layouts.app')
@section('title', 'Expense and Disbursement Tracking')
@section('content')
<div class="page-header"><div><h2>Expense and Disbursement Tracking</h2><p class="page-subtitle">Approved requests from Procurement & Financial Requests appear here automatically — record the actual expense/disbursement.</p></div></div>
<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>For Processing</h3><p class="card-amount">{{ $summary['for_processing'] ?? 0 }}</p><p class="summary-desc">Approved, awaiting actual expense + disbursement</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Completed</h3><p class="card-amount">{{ $summary['completed'] ?? 0 }}</p><p class="summary-desc">Actual posted to budget</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Approved Total</h3><p class="card-amount">₱{{ number_format($summary['total'] ?? 0,2) }}</p></div></div>
</div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Reference / payee"></div>
<div class="form-group" style="min-width:160px;"><label>Payment Status</label><select name="payment_status" class="form-control"><option value="">All</option><option value="for_disbursement" {{ request('payment_status')==='for_disbursement'?'selected':'' }}>For Disbursement</option><option value="paid" {{ request('payment_status')==='paid'?'selected':'' }}>Paid</option></select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.expenses.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Expense ID</th><th>Request ID</th><th>Vendor / Description</th><th>Amount</th><th>Payment Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)<tr>
<td><strong>{{ $r->reference_number }}</strong>@if($r->sourcePayable && $r->sourcePayable->financialRequest)<br><span class="summary-desc">{{ $r->sourcePayable->financialRequest->source_request_id ?: $r->sourcePayable->financialRequest->request_number }}</span>@endif</td>
<td>@if($r->sourcePayable && $r->sourcePayable->financialRequest)<span class="badge badge-green">Auto from AP</span><br><a href="{{ route('accountant.payables.show',$r->related_payable_id) }}">{{ $r->sourcePayable->ap_number ?? ('AP #'.$r->related_payable_id) }}</a>@if($r->processing_stage)<br><span class="summary-desc">{{ ucwords(strtolower($r->processing_stage)) }}</span>@endif @else<span class="badge badge-gray">Manual</span>@endif</td>
<td>{{ $r->payee }}<br><span class="summary-desc">{{ $r->expense_category }} · {{ $r->department }}</span></td>
<td>₱{{ number_format($r->amount,2) }}</td>
<td>@include('accountant.partials.status-badge',['status'=>$r->payment_status])</td>
<td><a href="{{ route('accountant.expenses.show',$r) }}" class="btn btn-sm btn-secondary">View</a>
@if($r->payment_status === 'for_disbursement')
<button class="btn btn-sm btn-primary" data-open-record-expense data-expense-id="{{ $r->id }}">Record Expense</button>
@endif</td></tr>
@empty<tr><td colspan="6" class="text-center">No disbursements found.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@include('accountant.expenses.partials.record-expense-modal')
@endsection