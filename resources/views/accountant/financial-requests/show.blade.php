@extends('layouts.app')
@section('title', 'Request Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->display_ref }} ({{ $record->request_type }})</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->status]) | Source: {{ $record->source_label }} | Decision: {{ $record->admin_decision ?? '—' }}</p></div><a href="{{ route('accountant.financial-requests.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->admin_remarks)<div class="alert alert-error"><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</div>@endif
@if($record->source_system !== 'internal' && $record->source_system)<div class="alert alert-info">Received from {{ $record->source_label }} ({{ $record->source_request_id }}) — the original record stays with the source subsystem. Review it here; corrections go back through return/withdraw, not direct edits.</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Request Details</h3><p><strong>Description:</strong> {{ $record->description }}</p><p><strong>Date:</strong> {{ isset($record->request_date) && $record->request_date ? \Carbon\Carbon::parse($record->request_date)->format('M d, Y') : '—' }} | <strong>Department:</strong> {{ $record->department }}</p><p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }}</p>@php $showBudgetId = $record->budget_plan_id ?? (($record->reference_type === \App\Models\BudgetPlan::class) ? $record->reference_id : null); @endphp<p><strong>Budget ID:</strong> @if($showBudgetId)#{{ $showBudgetId }}@if($record->budgetPlan) — {{ $record->budgetPlan->budget_name }} ({{ $record->budgetPlan->academic_year }}) | Rem ₱{{ number_format((float) $record->budgetPlan->remaining_amount,2) }}@endif@else —@endif</p><p><strong>Submitted:</strong> {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }} | <strong>Decided:</strong> {{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }}</p>@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View document</a></p>@endif</div>
<div class="dashboard-card"><h3>Linked Record</h3>@if($linked)<p><strong>{{ class_basename($record->reference_type) }} #{{ $record->reference_id }}</strong></p><pre style="white-space:pre-wrap;">{{ json_encode($linked->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>@else<p class="summary-desc">No linked record — standalone request.</p>@endif
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if($record->isEditable() && ($record->source_system ?? 'internal') === 'internal')<a href="{{ route('accountant.financial-requests.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a>@endif
@if($record->isEditable())<form method="POST" action="{{ route('accountant.financial-requests.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Admin Approval</button></form>@endif
@if($record->isPending())<form method="POST" action="{{ route('accountant.financial-requests.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
<div class="dashboard-card" style="margin-top:16px;"><h3>Budget Validation</h3>
@if($validation)
<p><strong>Budget Reference:</strong> {{ $validation['budget']->request_id }} — {{ $validation['budget']->budget_name }}</p>
<p><strong>Budget Period:</strong> {{ optional($validation['budget']->start_date)->format('M d, Y') ?? '—' }} – {{ optional($validation['budget']->end_date)->format('M d, Y') ?? '—' }} | <strong>Fund Source:</strong> {{ $validation['budget']->funding_source ?: '—' }}</p>
<p>Approved Budget: <strong>₱{{ number_format($validation['approved'],2) }}</strong> | Allocated: <strong>₱{{ number_format($validation['allocated'],2) }}</strong></p>
<p>Committed: <strong>₱{{ number_format($validation['committed'],2) }}</strong> | Utilized: <strong>₱{{ number_format($validation['utilized'],2) }}</strong> | Available: <strong>₱{{ number_format($validation['available'],2) }}</strong></p>
<p>Requested Amount: <strong>₱{{ number_format($record->amount,2) }}</strong>
@if($validation['valid'])<span class="badge badge-green">Budget Validated</span>@else<span class="badge badge-red">Budget Insufficient</span>@endif</p>
@if($validation['fund'])<p class="summary-desc">Fund {{ $validation['fund']->fund_name }} — available ₱{{ number_format((float) $validation['fund']->available_amount,2) }}</p>@endif
@elseif($record->budget_plan_id)<div class="alert alert-warning">Linked budget is not approved/active — validation unavailable.</div>
@else<p class="summary-desc">No linked budget — link an approved budget to validate financial impact.</p>@endif
</div>
@if(in_array($record->status, ['submitted','under_review']))
<div class="dashboard-card" style="margin-top:16px;"><h3>Accountant Review</h3>
<form method="POST" action="{{ route('accountant.financial-requests.review',$record) }}">
@csrf
<div class="form-group"><label>Recommended Amount (₱) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="recommended_amount" class="form-control" value="{{ old('recommended_amount', $record->metadata['recommended_amount'] ?? $record->amount) }}" required></div>
<div class="form-group"><label>Financial Remarks <span style="color:#64748b;font-weight:400;">(max 60)</span></label><textarea name="financial_remarks" class="form-control" rows="2" maxlength="60" placeholder="Required when returning to source... (max 60 chars)" style="resize:none;">{{ old('financial_remarks', $record->metadata['financial_remarks'] ?? '') }}</textarea></div>
<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;">
<button name="decision" value="return" class="btn btn-secondary" onclick="return confirm('Return this request to the source subsystem?')">Return to Source</button>
<button name="decision" value="forward" class="btn btn-primary">Forward to Admin</button>
</div>
</form>
</div>
@endif
@if($record->payable)
<div class="dashboard-card" style="margin-top:16px;"><div class="card-head-row"><h3>Expense &amp; Disbursement Fulfillment</h3></div>
<p><strong>Payable:</strong> <a href="{{ route('accountant.payables.show',$record->payable) }}">{{ $record->payable->ap_number }}</a> ({{ $record->payable->invoice_number }}) | <strong>Disbursement:</strong> @if($record->payable->disbursement)<a href="{{ route('accountant.expenses.show',$record->payable->disbursement) }}">{{ $record->payable->disbursement->reference_number }}</a> — {{ $record->payable->disbursement->processing_stage ? ucwords(strtolower($record->payable->disbursement->processing_stage)) : 'For Processing' }}@else <span class="summary-desc">generating…</span>@endif</p>
<p><strong>Approved (ceiling):</strong> ₱{{ number_format($record->approved_amount ?? $record->amount,2) }} | <strong>Actual:</strong> {{ $record->actual_amount ? '₱'.number_format($record->actual_amount,2) : '—' }} | <strong>Disbursed:</strong> ₱{{ number_format($record->payable->amount_paid,2) }}</p>
</div>
@endif
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
