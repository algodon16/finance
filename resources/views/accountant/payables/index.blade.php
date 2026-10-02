@extends('layouts.app')
@section('title', 'Accounts Payable')
@section('content')
<div class="page-header"><div><h2>Accounts Payable</h2><p class="page-subtitle">Vendor obligations linked to approved requests. Payment requires admin approval.</p></div></div>
<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Overdue</h3><p class="card-amount">{{ $summary['overdue'] ?? 0 }}</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Total / Paid</h3><p class="card-amount">₱{{ number_format($summary['total'] ?? 0,2) }} / ₱{{ number_format($summary['paid'] ?? 0,2) }}</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Pending Approval</h3><p class="card-amount">{{ $summary['pending'] ?? 0 }}</p></div></div>
</div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 220px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Invoice no. / vendor"></div>
<div class="form-group" style="min-width:150px;"><label>Status</label><select name="approval_status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Pending Approval','approved'=>'Approved','rejected'=>'Rejected','for_revision'=>'For Revision','cancelled'=>'Cancelled'] as $k=>$l)<option value="{{ $k }}" {{ request('approval_status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:150px;"><label>Department</label><select name="department" class="form-control"><option value="">All departments</option>@foreach($departments ?? [] as $d)<option value="{{ $d }}" {{ request('department')===$d?'selected':'' }}>{{ $d }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:140px;"><label>Due From</label><input type="date" name="due_from" class="form-control" value="{{ request('due_from') }}"></div>
<div class="form-group" style="min-width:140px;"><label>Due To</label><input type="date" name="due_to" class="form-control" value="{{ request('due_to') }}"></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.payables.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>AP ID</th><th>Invoice No.</th><th>Vendor</th><th>Source</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)<tr>
<td>#{{ $r->id }}</td>
<td><strong>{{ $r->invoice_number }}</strong></td>
<td>{{ $r->source_vendor }}</td>
<td>{{ $r->source_label }}</td>
<td>₱{{ number_format($r->amount,2) }}</td>
<td>{{ optional($r->due_date)->format('M d, Y') }}</td>
<td>@include('accountant.partials.status-badge',['status'=>$r->approval_status ?? 'draft'])</td>
<td><a href="{{ route('accountant.payables.show',$r) }}" class="btn btn-sm btn-secondary">View</a>@if(in_array($r->approval_status ?? 'draft',['draft','rejected','for_revision','revision','cancelled'])) <a href="{{ route('accountant.payables.edit',$r) }}" class="btn btn-sm btn-secondary">Edit</a>@endif</td>
</tr>
@empty<tr><td colspan="8"></td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
