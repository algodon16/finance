@extends('layouts.app')
@section('title', 'Revenue Management')
@section('content')
<div class="page-header"><div><h2>Revenue Management</h2><p class="page-subtitle">Prepare revenue transactions. Same <code>payments</code> table as Admin → Revenue Management. Pending = draft, Under Review = pending approval.</p></div><a href="{{ route('accountant.revenue.create') }}" class="btn btn-primary">Record Revenue</a></div>
<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Approved Total</h3><p class="card-amount">₱{{ number_format($summary['total'] ?? 0,2) }}</p></div></div>
</div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Reference / student / description"></div>
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','submitted'=>'Pending Approval','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled'] as $k=>$l)<option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:160px;"><label>Payment Method</label><select name="payment_method" class="form-control"><option value="">All methods</option>@foreach(['cash','bank_transfer','gcash','maya','other'] as $m)<option value="{{ $m }}" {{ request('payment_method')===$m?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$m)) }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:150px;"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
<div class="form-group" style="min-width:150px;"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.revenue.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Student</th><th>Description</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $p)<tr><td>{{ optional($p->payment_date)->format('M d, Y') }}</td><td>{{ $p->student->full_name ?? 'N/A' }}<br><span class="summary-desc">{{ $p->student->student_number ?? '' }}</span></td><td>{{ $p->description ?? '—' }}</td><td>₱{{ number_format($p->amount,2) }}</td><td>{{ ucfirst(str_replace('_',' ',$p->payment_method)) }}</td><td>{{ $p->reference_number ?? '—' }}</td><td>@include('accountant.partials.status-badge',['status'=>$p->status==='pending'?'draft':($p->status==='under_review'?'submitted':$p->status)])</td><td><a href="{{ route('accountant.revenue.show',$p) }}" class="btn btn-sm btn-secondary">View</a></td></tr>
@empty<tr><td colspan="8"></td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
