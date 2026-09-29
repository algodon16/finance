@extends('layouts.admin')
@section('title', 'Procurement and Financial Requests')
@section('content')
<div class="page-header"><h2>Procurement and Financial Requests</h2><a class="btn btn-primary" href="{{ route('admin.procurement.create') }}">Create Request</a></div>
<div class="fms-stat-grid">
<div class="fms-stat"><h4>Pending</h4><p class="val">{{ $counts['pending'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Approved</h4><p class="val">{{ $counts['approved'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Rejected / Revision</h4><p class="val">{{ $counts['rejected'] ?? 0 }}</p></div>
</div>
<div class="fms-panel"><h3>Accountant Financial Requests (same records)</h3>
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;align-items:flex-end;">
<div><label style="font-size:0.8rem;">Status</label><br>
<select name="status" class="form-control" style="min-width:160px;">
<option value="">All statuses</option>
@foreach(['submitted','approved','rejected','revision','cancelled','draft'] as $s)
<option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ \App\Services\WorkflowService::label($s) }}</option>
@endforeach
</select></div>
<div><button class="btn btn-secondary" type="submit">Filter</button> <a class="btn btn-secondary" href="{{ route('admin.financial-requests.index') }}">Clear</a></div>
</form>
<table class="fms-table"><thead><tr><th>Request No.</th><th>Type</th><th>Budget ID</th><th style="text-align:right;">Amount</th><th>Prepared By</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($records as $r)@php $bId = $r->budget_plan_id ?? (($r->reference_type === \App\Models\BudgetPlan::class) ? $r->reference_id : null); @endphp<tr><td><a href="{{ route('admin.financial-requests.show', $r) }}">{{ $r->request_number }}</a></td><td>{{ $r->request_type }}</td><td>@if($bId)<strong>#{{ $bId }}</strong>@if($r->budgetPlan)<span style="color:#64748b;"> {{ $r->budgetPlan->budget_name }}</span>@endif@else<span style="color:#64748b;">—</span>@endif</td><td style="text-align:right;">P{{ number_format($r->amount,2) }}</td><td>{{ $r->preparer->name ?? '—' }}</td><td><span class="status">{{ \App\Services\WorkflowService::label($r->status) }}</span></td><td><a class="btn btn-sm btn-secondary" href="{{ route('admin.financial-requests.show', $r) }}">Review</a></td></tr>
@empty<tr><td colspan="7" style="color:#64748b;">No financial requests.</td></tr>@endforelse
</tbody></table><div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
