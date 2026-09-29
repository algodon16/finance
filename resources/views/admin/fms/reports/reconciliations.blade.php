@extends('layouts.admin')
@section('title', 'Reconciliation Review')
@section('content')
<div class="page-header"><h2>Reconciliation Review</h2><p style="color:#64748b;">Same records the Accountant submitted. Approval makes them part of official financial records.</p></div>
<div class="fms-stat-grid">
<div class="fms-stat"><h4>Pending</h4><p class="val">{{ $counts['pending'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Approved</h4><p class="val">{{ $counts['approved'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Rejected / Revision</h4><p class="val">{{ $counts['rejected'] ?? 0 }}</p></div>
</div>
<div class="fms-panel">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;"><select name="status" class="form-control" style="max-width:200px;"><option value="">All statuses</option>@foreach(['submitted','approved','rejected','revision','cancelled','draft'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ \App\Services\WorkflowService::label($s) }}</option>@endforeach</select><button class="btn btn-secondary">Filter</button></form>
<table class="fms-table"><thead><tr><th>Reference</th><th>Date</th><th style="text-align:right;">System</th><th style="text-align:right;">Actual</th><th style="text-align:right;">Variance</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($records as $r)<tr><td>{{ $r->reference_number }}</td><td>{{ $r->reconciliation_date }}</td><td style="text-align:right;">P{{ number_format($r->system_amount,2) }}</td><td style="text-align:right;">P{{ number_format($r->actual_amount,2) }}</td><td style="text-align:right;">P{{ number_format($r->variance,2) }}</td><td><span class="status">{{ \App\Services\WorkflowService::label($r->status) }}</span></td>
<td><div class="fms-actions">@if(in_array($r->status,['submitted','under_review','draft']))<form method="POST" action="{{ route('admin.reports.reconciliations.approve',$r) }}">@csrf<button class="btn btn-sm btn-primary">Approve</button></form><form method="POST" action="{{ route('admin.reports.reconciliations.reject',$r) }}">@csrf<input type="text" name="rejection_reason" placeholder="Reason (required)" class="form-control" required style="max-width:180px;"><button class="btn btn-sm btn-danger">Reject</button></form>@else<span style="color:#64748b;">Decided</span>@endif</div></td></tr>
@empty<tr><td colspan="7" style="color:#64748b;">No reconciliation records.</td></tr>@endforelse
</tbody></table><div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
