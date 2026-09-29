@extends('layouts.app')
@section('title', 'Reconciliation Records — Financial Reporting and Compliance')
@section('content')
<div class="page-header"><div><h2>Reconciliation Records</h2>
<p class="page-subtitle">Accountant prepares and reconciles. Submit to admin when review is required. Part of Financial Reporting and Compliance.</p></div><div style="display:flex;gap:8px;"><a href="{{ route('accountant.financial-reports.index') }}" class="btn btn-secondary">Reports</a><a href="{{ route('accountant.reconciliation.index') }}" class="btn btn-secondary">Reconciliation</a><a href="{{ route('accountant.reconciliation-records.create') }}" class="btn btn-primary">Prepare Reconciliation</a></div></div>
<form method="GET" class="filter-form" style="align-items:flex-end;margin-top:12px;">
<div class="form-group" style="min-width:160px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft'=>'Draft','in_progress'=>'In Progress','reconciled'=>'Reconciled','with_variance'=>'With Variance','submitted'=>'Submitted','reviewed'=>'Reviewed'] as $k=>$l)<option value="{{ $k }}" {{ request('status')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.reconciliation-records.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Reference</th><th>Date</th><th>System</th><th>Actual</th><th>Variance</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($records as $r)<tr><td>{{ $r->reference_number }}</td><td>{{ optional($r->reconciliation_date)->format('M d, Y') }}</td><td>₱{{ number_format($r->system_amount,2) }}</td><td>₱{{ number_format($r->actual_amount,2) }}</td><td>₱{{ number_format($r->variance,2) }}</td><td><span class="badge badge-gray">{{ $r->status }}</span></td><td><a href="{{ route('accountant.reconciliation-records.show',$r) }}" class="btn btn-sm btn-secondary">View</a></td></tr>@empty<tr><td colspan="7" class="text-center">No records</td></tr>@endforelse</tbody></table></div>
{{ $records->links() }}
@endsection
