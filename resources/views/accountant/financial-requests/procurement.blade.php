@extends('layouts.app')
@section('title', 'Procurement Requests')
@section('content')
<div class="page-header"><div><h2>Procurement and Financial Requests</h2><p class="page-subtitle">Procurement requests submitted in the system (read-only). To request procurement, prepare a Financial Request of matching type.</p></div><a href="{{ route('accountant.financial-requests.index') }}" class="btn btn-secondary">Back to Financial Requests</a></div>
<div class="dashboard-card"><div class="card-head-row"><h3>Procurement Requests</h3><a href="{{ route('accountant.financial-requests.create') }}" class="btn btn-primary">Prepare Financial Request</a></div>
<form method="GET" class="filter-form" style="align-items:flex-end;"><div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Request no. / item"></div><div class="form-group" style="min-width:160px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','ordered'=>'Ordered','fulfilled'=>'Fulfilled','cancelled'=>'Cancelled'] as $k=>$l)<option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div><div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button></div></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Request No.</th><th>Department</th><th>Item</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
@forelse($records as $r)<tr><td>{{ $r->request_number }}</td><td>{{ $r->requesting_department ?? '—' }}</td><td>{{ $r->item_description ?? '—' }}</td><td style="text-align:right;">₱{{ number_format($r->total_amount ?? $r->estimated_cost ?? 0,2) }}</td><td>@include('accountant.partials.status-badge',['status'=>$r->status])</td></tr>
@empty<tr><td colspan="5" class="text-center">No procurement requests.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
