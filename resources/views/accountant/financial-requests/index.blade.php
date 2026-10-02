@extends('layouts.app')
@section('title', 'Procurement and Financial Requests')
@section('content')
<div class="page-header"><div><h2>Incoming Financial Requests</h2><p class="page-subtitle">Requests received from integrated subsystems, validated against approved budgets. Original source IDs are preserved — nothing is recreated.</p></div><div style="display:flex;gap:8px;"><button type="button" class="btn btn-primary" data-open-simulate>+ Simulate Incoming Request</button></div></div>

<div class="summary-cards">
<div class="dashboard-card acct-card"><div class="card-content"><h3>Incoming Requests</h3><p class="card-amount">{{ $summary['incoming'] ?? 0 }}</p><p class="summary-desc">Submitted, awaiting review</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>For Review</h3><p class="card-amount">{{ $summary['review'] ?? 0 }}</p><p class="summary-desc">Under financial review</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Approved</h3><p class="card-amount">{{ $summary['approved'] ?? 0 }}</p><p class="summary-desc">Cleared for processing</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Completed</h3><p class="card-amount">{{ $summary['completed'] ?? 0 }}</p><p class="summary-desc">Fully processed</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Returned / Rejected</h3><p class="card-amount">{{ $summary['returned'] ?? 0 }}</p><p class="summary-desc">Sent back or declined</p></div></div>
<div class="dashboard-card acct-card"><div class="card-content"><h3>Total Financial Impact</h3><p class="card-amount">₱{{ number_format($summary['impact'] ?? 0,2) }}</p><p class="summary-desc">Approved + completed</p></div></div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
<div class="card-head-row"><h3>Requests</h3></div>
<form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="flex:0 1 200px;min-width:160px;"><label>Search</label><input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Request ID / dept / purpose"></div>
<div class="form-group" style="min-width:160px;"><label>Source System</label><select name="source_system" class="form-control"><option value="">All sources</option>@foreach(\App\Models\FinancialRequest::SOURCES as $k=>$l)<option value="{{ $k }}" {{ request('source_system')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:150px;"><label>Request Type</label><select name="request_type" class="form-control"><option value="">All types</option>@foreach(\App\Models\FinancialRequest::TYPES as $t)<option value="{{ $t }}" {{ request('request_type')===$t?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$t)) }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:150px;"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(\App\Models\FinancialRequest::STATUSES as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','for_revision'=>'For Revision','completed'=>'Completed'][$s] ?? ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.financial-requests.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Request ID</th><th>Source System</th><th>Department / Type</th><th>Budget Reference</th><th style="text-align:right;">Requested</th><th>Financial Impact</th><th>Received</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $r)
@php
$bId = $r->budget_plan_id ?? (($r->reference_type === \App\Models\BudgetPlan::class) ? $r->reference_id : null);
$v = ($validMap ?? [])[$r->id] ?? null;
$isDemo = ! empty($r->metadata['demo']);
$hasSourceNo = (bool) $r->source_request_id;
$hasBudget = (bool) $r->budgetPlan;
$noBudget = ! $bId;
$validOk = (bool) ($v && $v['valid']);
$validBad = (bool) ($v && ! $v['valid']);
$noAppr = (bool) ($bId && ! $v);
@endphp
<tr>
<td><strong>{{ $r->display_ref }}</strong>@if($isDemo) <span class="badge badge-red">Demo</span>@endif @if($hasSourceNo)<br><span class="summary-desc">{{ $r->request_number }}</span>@endif</td>
<td>{{ $r->source_label }}</td>
<td>{{ $r->department ?: '—' }}<br><span class="summary-desc">{{ ucfirst(str_replace('_',' ',$r->request_type)) }}</span></td>
<td>@if($bId)<strong>{{ $r->budgetPlan?->request_id ?? ('BR-'.$bId) }}</strong>@endif @if($hasBudget)<br><span class="summary-desc">{{ \Str::limit($r->budgetPlan->budget_name, 30) }}</span>@endif @if($noBudget)<span class="summary-desc">—</span>@endif</td>
<td style="text-align:right;">₱{{ number_format($r->amount,2) }}</td>
<td>@if($v)<span class="summary-desc">Avail ₱{{ number_format($v['available'],2) }}</span><br>@endif @if($validOk)<span class="badge badge-green">Budget Validated</span>@endif @if($validBad)<span class="badge badge-red">Budget Insufficient</span>@endif @if($noAppr)<span class="badge badge-gray">No approved budget</span>@endif @if($noBudget)<span class="summary-desc">No budget link</span>@endif</td>
<td style="white-space:nowrap;">{{ ($r->received_at ?? $r->created_at)?->format('M d, Y') }}</td>
<td>@include('accountant.partials.status-badge',['status'=>$r->status])</td>
<td><a href="{{ route('accountant.financial-requests.show',$r) }}" class="btn btn-sm btn-secondary">View</a></td>
</tr>
@empty<tr><td colspan="9" class="text-center muted-text">No requests found.</td></tr>
@endforelse</tbody></table></div>
{{ $records->links() }}</div>
@include('financial-requests.partials.simulate-modal', ['simulateAction' => route('accountant.financial-requests.simulate'), 'departments' => $departments ?? [], 'budgets' => $budgets ?? []])
@endsection
