@extends('layouts.app')
@section('title', 'Fund Management and Allocation')
@section('content')
<div class="page-header"><div><h2>Fund Management and Allocation</h2><p class="page-subtitle">Allocate approved budgets to departments. Approved Budget → Allocation → Expense. Cannot exceed available.</p></div><a href="{{ route('accountant.fund-allocations.create') }}" class="btn btn-primary">Prepare Allocation</a></div>
<div class="dashboard-card"><h3>Available Approved Funds</h3><div class="table-responsive"><table class="table"><thead><tr><th>Fund</th><th>Current</th><th>Reserved</th><th>Available</th></tr></thead><tbody>
@forelse($funds as $f)<tr><td>{{ $f->fund_name }}</td><td>₱{{ number_format($f->current_balance,2) }}</td><td>₱{{ number_format($f->reserved_amount,2) }}</td><td><strong>₱{{ number_format($f->available_amount,2) }}</strong></td></tr>
@empty<tr><td colspan="4" class="text-center">No active funds</td></tr>@endforelse
</tbody></table></div></div>
<div class="dashboard-card" style="margin-top:16px;"><div class="card-head-row"><h3>Allocations</h3></div>
<form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','for_revision'=>'For Revision','revision'=>'Revision','rejected'=>'Rejected','cancelled'=>'Cancelled','allocated'=>'Allocated'] as $k=>$l)<option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:160px;"><label>Fund</label><select name="fund_id" class="form-control"><option value="">All funds</option>@foreach($funds as $f)<option value="{{ $f->id }}" {{ request('fund_id')==$f->id?'selected':'' }}>{{ $f->fund_name }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.fund-allocations.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Fund → Target</th><th>Budget</th><th>Amount</th><th>Rev</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)<tr><td><strong>{{ $r->fund->fund_name ?? 'N/A' }}</strong> → {{ $r->allocated_to }}<br><span class="summary-desc">{{ $r->purpose }}</span></td><td>@if($r->budget_plan_id)<strong>#{{ $r->budget_plan_id }}</strong> — {{ $r->budgetPlan->budget_name ?? '' }}@else — @endif</td><td>₱{{ number_format($r->amount,2) }}</td><td>{{ $r->revision_number ?? 0 }}</td><td>@include('accountant.partials.status-badge',['status'=>$r->status])</td><td><a href="{{ route('accountant.fund-allocations.show',$r) }}" class="btn btn-sm btn-secondary">View</a></td></tr>
@empty<tr><td colspan="6"></td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
