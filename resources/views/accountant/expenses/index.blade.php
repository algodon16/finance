@extends('layouts.app')
@section('title', 'Expense and Disbursement Tracking')
@section('content')
<div class="page-header"><div><h2>Expense and Disbursement Tracking</h2><p class="page-subtitle">Approved APs appear here automatically as disbursements — no manual re-entry. Standalone proposals can still be created below.</p></div><a href="{{ route('accountant.expenses.create') }}" class="btn btn-primary">Create Standalone Proposal</a></div>
<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Approved Total</h3><p class="card-amount">₱{{ number_format($summary['total'] ?? 0,2) }}</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Auto Disbursements</h3><p class="card-amount">{{ $summary['auto'] ?? 0 }}</p></div></div>
</div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Reference / payee"></div>
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="approval_status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','for_revision'=>'For Revision','revision'=>'Revision','cancelled'=>'Cancelled'] as $k=>$l)<option value="{{ $k }}" {{ request('approval_status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:160px;"><label>Origin</label><select name="origin" class="form-control"><option value="">All origins</option><option value="auto" {{ request('origin')==='auto'?'selected':'' }}>Auto from AP</option><option value="manual" {{ request('origin')==='manual'?'selected':'' }}>Manual proposals</option></select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.expenses.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Expense ID</th><th>Source</th><th>Vendor / Description</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)<tr>
<td><strong>{{ $r->reference_number }}</strong></td>
<td>@if($r->related_payable_id)<span class="badge badge-green">Auto from AP</span><br><a href="{{ route('accountant.payables.show',$r->related_payable_id) }}">{{ $r->sourcePayable->ap_number ?? ('AP #'.$r->related_payable_id) }}</a>@else<span class="badge badge-gray">Manual</span>@endif</td>
<td>{{ $r->payee }}<br><span class="summary-desc">{{ $r->expense_category }} · {{ $r->department }}</span></td>
<td>₱{{ number_format($r->amount,2) }}</td>
<td>{{ optional($r->proposed_payment_date)->format('M d, Y') ?? '—' }}</td>
<td>@include('accountant.partials.status-badge',['status'=>$r->approval_status])<br><span class="summary-desc">{{ \App\Services\WorkflowService::label($r->payment_status) }}</span></td>
<td><a href="{{ route('accountant.expenses.show',$r) }}" class="btn btn-sm btn-secondary">Open</a></td></tr>
@empty<tr><td colspan="7"></td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
