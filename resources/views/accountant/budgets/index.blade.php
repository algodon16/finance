@extends('layouts.app')
@section('title', 'Budget Planning and Allocation Overview')
@section('content')
<div class="page-header"><div><h2>Budget Planning and Allocation Overview</h2><p class="page-subtitle">All budget plans with proposed totals and approval status. Item breakdown is now under Procurement and Financial Requests.</p></div><a href="{{ route('accountant.budgets.create') }}" class="btn btn-primary">Create Budget Plan</a></div>

<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Total Proposed</h3><p class="card-amount">₱{{ number_format($summary['total'] ?? 0,2) }}</p><p class="summary-desc">All plans</p></div></div>
</div>

<div class="dashboard-card">
<div class="card-head-row"><h3>Plans</h3></div>
<form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search Plan</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Plan name / department / program"></div>
<div class="form-group" style="min-width:170px;"><label>Academic Year</label><select name="fiscal_year" class="form-control"><option value="">All Academic Years</option>@foreach(($years ?? []) as $y)<option value="{{ $y }}" {{ request('fiscal_year')===$y?'selected':'' }}>{{ $y }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','active'=>'Active','for_revision'=>'For Revision','revision'=>'Revision','rejected'=>'Rejected','cancelled'=>'Cancelled','inactive'=>'Inactive','closed'=>'Closed'] as $k=>$l)<option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.budgets.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Budget ID</th><th>Plan</th><th>Academic Year</th><th>Department</th><th>Budget Period</th><th style="text-align:right;">Total Proposed</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($plans as $p)
<tr>
<td><strong>#{{ $p->id }}</strong></td>
<td><strong>{{ $p->budget_name }}</strong><br><span class="summary-desc">{{ $p->budget_category }}</span></td>
<td>{{ $p->fiscal_year }}</td>
<td>{{ $p->department }}</td>
<td style="white-space:nowrap;">{{ optional($p->start_date)->format('M d, Y') ?? '—' }} – {{ optional($p->end_date)->format('M d, Y') ?? '—' }}</td>
<td style="text-align:right;font-weight:700;">₱{{ number_format($p->allocated_amount,2) }}</td>
<td>@include('accountant.partials.status-badge',['status'=>$p->status])</td>
<td>
<div style="white-space:nowrap;display:flex;gap:4px;">
<a href="{{ route('accountant.budgets.show',$p) }}" class="btn btn-sm btn-secondary">View</a>
@if(in_array($p->status,['draft','for_revision','revision','rejected','cancelled']))
<a href="{{ route('accountant.budgets.edit',$p) }}" class="btn btn-sm btn-secondary">Edit</a>
@endif
@if(in_array($p->status,['draft','cancelled']))
<form method="POST" action="{{ route('accountant.budgets.draft.destroy',$p) }}" onsubmit="return confirm('Delete this draft? History is preserved.');" style="display:inline;">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Delete</button></form>
@endif
</div>
</td>
</tr>
@empty<tr><td colspan="8"></td></tr>
@endforelse</tbody></table></div>
{{ $plans->links() }}
</div>
@endsection
