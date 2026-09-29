@extends('layouts.app')
@section('title', 'Request Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->request_number }} ({{ $record->request_type }})</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->status]) | Decision: {{ $record->admin_decision ?? '—' }}</p></div><a href="{{ route('accountant.financial-requests.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->admin_remarks)<div class="alert alert-error"><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Request Details</h3><p><strong>Description:</strong> {{ $record->description }}</p><p><strong>Date:</strong> {{ isset($record->request_date) && $record->request_date ? \Carbon\Carbon::parse($record->request_date)->format('M d, Y') : '—' }} | <strong>Department:</strong> {{ $record->department }}</p><p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }}</p>@php $showBudgetId = $record->budget_plan_id ?? (($record->reference_type === \App\Models\BudgetPlan::class) ? $record->reference_id : null); @endphp<p><strong>Budget ID:</strong> @if($showBudgetId)#{{ $showBudgetId }}@if($record->budgetPlan) — {{ $record->budgetPlan->budget_name }} ({{ $record->budgetPlan->fiscal_year }}) | Rem ₱{{ number_format((float) $record->budgetPlan->remaining_amount,2) }}@endif@else —@endif</p><p><strong>Submitted:</strong> {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }} | <strong>Decided:</strong> {{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }}</p>@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View document</a></p>@endif</div>
<div class="dashboard-card"><h3>Linked Record</h3>@if($linked)<p><strong>{{ class_basename($record->reference_type) }} #{{ $record->reference_id }}</strong></p><pre style="white-space:pre-wrap;">{{ json_encode($linked->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>@else<p class="summary-desc">No linked record — standalone request.</p>@endif
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if($record->isEditable())<a href="{{ route('accountant.financial-requests.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a><form method="POST" action="{{ route('accountant.financial-requests.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Admin Approval</button></form>@endif
@if($record->isPending())<form method="POST" action="{{ route('accountant.financial-requests.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
<div class="dashboard-card" style="margin-top:16px;"><h3>Budget Items</h3>
@php $items = is_array($record->metadata ?? null) ? ($record->metadata['items'] ?? []) : []; @endphp
@if(count($items))
<div class="table-responsive"><table class="table"><thead><tr><th>Item Name</th><th>Category</th><th>Supplier</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Cost</th><th style="text-align:right;">Line Total</th></tr></thead><tbody>
@foreach($items as $it)<tr><td>{{ $it['item_name'] ?? '—' }}</td><td>{{ $it['category'] ?? '—' }}</td><td>{{ $it['supplier'] ?? '—' }}</td><td style="text-align:right;">{{ number_format($it['quantity'] ?? 0) }}</td><td style="text-align:right;">₱{{ number_format((float) ($it['unit_cost'] ?? 0),2) }}</td><td style="text-align:right;font-weight:700;">₱{{ number_format((float) ($it['line_total'] ?? 0),2) }}</td></tr>@endforeach
</tbody><tfoot><tr><td colspan="5" style="text-align:right;"><strong>Total</strong></td><td style="text-align:right;"><strong>₱{{ number_format($record->amount,2) }}</strong></td></tr></tfoot></table></div>
@else
<p class="summary-desc">No item breakdown (legacy request — total stored in header).</p>
@endif
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
