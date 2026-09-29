@extends('layouts.admin')
@section('title', 'Financial Request Review')
@section('content')
<div class="page-header"><h2>{{ $record->request_number }} ({{ $record->request_type }})</h2><a class="btn btn-secondary" href="{{ route('admin.financial-requests.index') }}">Back</a></div>
<div class="fms-two">
<div class="fms-panel"><h3>Request Details</h3>
<table class="fms-table"><tbody>
<tr><td style="width:200px;color:#64748b;">Status</td><td><span class="status">{{ \App\Services\WorkflowService::label($record->status) }}</span> | Revision {{ $record->revision_number ?? 0 }}</td></tr>
<tr><td style="color:#64748b;">Prepared By</td><td>{{ $record->preparer->name ?? '—' }} | {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td style="color:#64748b;">Description</td><td>{{ $record->description }}</td></tr>
<tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount,2) }}</strong></td></tr>
<tr><td style="color:#64748b;">Department</td><td>{{ $record->department }}</td></tr>
<tr><td style="color:#64748b;">Date</td><td>{{ isset($record->request_date) && $record->request_date ? \Carbon\Carbon::parse($record->request_date)->format('M d, Y') : '—' }}</td></tr>
@php $adminBudgetId = $record->budget_plan_id ?? (($record->reference_type === \App\Models\BudgetPlan::class) ? $record->reference_id : null); @endphp
<tr><td style="color:#64748b;">Budget ID</td><td>@if($adminBudgetId)<strong>#{{ $adminBudgetId }}</strong>@if($record->budgetPlan) — {{ $record->budgetPlan->budget_name }} ({{ $record->budgetPlan->fiscal_year }}) | Rem P{{ number_format((float) $record->budgetPlan->remaining_amount,2) }}@endif@else —@endif</td></tr>
@if($record->supporting_document)<tr><td style="color:#64748b;">Document</td><td><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank">View</a></td></tr>@endif
@if($record->admin_remarks)<tr><td style="color:#64748b;">Admin Remarks</td><td>{{ $record->admin_remarks }}</td></tr>@endif
</tbody></table>
@if($linked)<h3 style="margin-top:12px;">Linked Record ({{ class_basename($record->reference_type) }} #{{ $record->reference_id }})</h3><pre style="white-space:pre-wrap;max-height:300px;overflow:auto;">{{ json_encode($linked->toArray(), JSON_PRETTY_PRINT) }}</pre>@endif
@php $adminItems = is_array($record->metadata ?? null) ? ($record->metadata['items'] ?? []) : []; @endphp
@if(count($adminItems))<h3 style="margin-top:12px;">Budget Items</h3><table class="fms-table"><thead><tr><th>Item Name</th><th>Category</th><th>Supplier</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Total</th></tr></thead><tbody>
@foreach($adminItems as $it)<tr><td>{{ $it['item_name'] ?? '—' }}</td><td>{{ $it['category'] ?? '—' }}</td><td>{{ $it['supplier'] ?? '—' }}</td><td style="text-align:right;">{{ number_format($it['quantity'] ?? 0) }}</td><td style="text-align:right;">P{{ number_format((float) ($it['unit_cost'] ?? 0),2) }}</td><td style="text-align:right;font-weight:700;">P{{ number_format((float) ($it['line_total'] ?? 0),2) }}</td></tr>@endforeach
</tbody></table>@endif
</div>
<div>
<div class="fms-panel"><h3>Review — Approve / Reject (same record)</h3>
@if(in_array($record->status, ['submitted','under_review']))
<div style="display:flex;gap:16px;flex-wrap:wrap;">
<form method="POST" action="{{ route('admin.financial-requests.approve', $record) }}">@csrf<textarea name="admin_remarks" class="form-control" rows="2" placeholder="Approval remarks (optional)"></textarea><button class="btn btn-primary" style="margin-top:8px;" type="submit">Approve</button></form>
<form method="POST" action="{{ route('admin.financial-requests.reject', $record) }}">@csrf<input type="text" name="rejection_reason" class="form-control" placeholder="Rejection reason (required)" required><button class="btn btn-danger" style="margin-top:8px;" type="submit">Reject</button></form>
</div>
@else<p style="color:#64748b;">Decision: {{ $record->admin_decision ?? $record->status }} by {{ $record->decider->name ?? '—' }} on {{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }}.</p>@endif
</div>
<div class="fms-panel"><h3>Approval History</h3><table class="fms-table"><thead><tr><th>Date</th><th>User</th><th>Action</th></tr></thead><tbody>
@forelse($history ?? [] as $h)<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }}</td><td>{{ $h->action }} — {{ $h->description }}</td></tr>@empty<tr><td colspan="3">No history</td></tr>@endforelse
</tbody></table></div>
</div>
</div>
@endsection
