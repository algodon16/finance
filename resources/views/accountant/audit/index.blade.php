@extends('layouts.app')
@section('title', 'Security and Audit Trail')
@section('content')
<div class="page-header"><div><h2>Security and Audit Trail</h2><p class="page-subtitle">Your own relevant activity (read-only). Full trail is visible to Admin.</p></div></div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="min-width:160px;"><label>Action</label><select name="action" class="form-control"><option value="">All actions</option>@foreach($actions as $a)<option value="{{ $a }}" {{ request('action')===$a?'selected':'' }}>{{ $a }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:160px;"><label>Module</label><select name="module" class="form-control"><option value="">All modules</option>@foreach($modules as $m)<option value="{{ $m }}" {{ request('module')===$m?'selected':'' }}>{{ $m }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.audit.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Action</th><th>Module</th><th>Record</th><th>Details</th></tr></thead><tbody>
@forelse($records as $r)<tr><td>{{ $r->created_at?->format('M d, Y h:i A') }}</td><td><span class="badge badge-gray">{{ $r->action }}</span></td><td>{{ $r->module }}</td><td>{{ $r->reference_no ?? $r->record_id ?? '—' }}</td><td>{{ $r->description ?? '—' }}</td></tr>
@empty<tr><td colspan="5" class="text-center">No activity yet.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div>
@endsection
