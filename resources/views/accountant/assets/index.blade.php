@extends('layouts.app')
@section('title', 'Asset and Depreciation Management')
@section('content')
<div class="page-header"><div><h2>Asset and Depreciation Management</h2><p class="page-subtitle">Prepare asset records. Same <code>assets</code> table as Admin. Depreciation uses approved records (straight-line, server-side).</p></div><a href="{{ route('accountant.assets.create') }}" class="btn btn-primary">Prepare Asset</a></div>
<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Approved Book Value</h3><p class="card-amount">₱{{ number_format($summary['book'] ?? 0,2) }}</p></div></div>
</div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name / code"></div>
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="approval_status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Pending Approval','approved'=>'Approved','rejected'=>'Rejected','revision'=>'For Revision','cancelled'=>'Cancelled'] as $k=>$l)<option value="{{ $k }}" {{ request('approval_status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.assets.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Code → Name</th><th>Category</th><th style="text-align:right;">Cost</th><th style="text-align:right;">Book Value</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)<tr><td><strong>{{ $r->asset_code }}</strong><br>{{ $r->asset_name }}</td><td>{{ $r->asset_category }}</td><td style="text-align:right;">₱{{ number_format($r->acquisition_cost,2) }}</td><td style="text-align:right;">₱{{ number_format($r->book_value,2) }}</td><td>@include('accountant.partials.status-badge',['status'=>$r->approval_status ?? 'draft'])</td><td><a href="{{ route('accountant.assets.show',$r) }}" class="btn btn-sm btn-secondary">View</a></td></tr>
@empty<tr><td colspan="6"></td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
