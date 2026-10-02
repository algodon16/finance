@extends('layouts.admin')
@section('title', 'Financial Request Review')
@section('content')
<div class="page-header"><h2>{{ $record->display_ref }} ({{ $record->request_type }})</h2><a class="btn btn-secondary" href="{{ route('admin.financial-requests.index') }}">Back</a></div>
<div class="fms-two">
<div class="fms-panel"><h3>Request Details</h3>
<table class="fms-table"><tbody>
<tr><td style="width:200px;color:#64748b;">Source System</td><td><strong>{{ $record->source_label }}</strong>@if($record->source_request_id)<br><span style="color:#64748b;font-size:.8rem;">Internal ref: {{ $record->request_number }}</span>@endif</td></tr>
<tr><td style="width:200px;color:#64748b;">Status</td><td><span class="status">{{ \App\Services\WorkflowService::label($record->status) }}</span> | Revision {{ $record->revision_number ?? 0 }}</td></tr>
<tr><td style="color:#64748b;">Prepared By</td><td>{{ $record->preparer->name ?? '—' }} | {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td style="color:#64748b;">Description</td><td>{{ $record->description }}</td></tr>
<tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount,2) }}</strong></td></tr>
<tr><td style="color:#64748b;">Department</td><td>{{ $record->department }}</td></tr>
<tr><td style="color:#64748b;">Date</td><td>{{ isset($record->request_date) && $record->request_date ? \Carbon\Carbon::parse($record->request_date)->format('M d, Y') : '—' }}</td></tr>
@php $adminBudgetId = $record->budget_plan_id ?? (($record->reference_type === \App\Models\BudgetPlan::class) ? $record->reference_id : null); @endphp
<tr><td style="color:#64748b;">Budget ID</td><td>@if($adminBudgetId)<strong>#{{ $adminBudgetId }}</strong>@if($record->budgetPlan) — {{ $record->budgetPlan->budget_name }} ({{ $record->budgetPlan->academic_year }}) | Rem P{{ number_format((float) $record->budgetPlan->remaining_amount,2) }}@endif@else —@endif</td></tr>
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
<div class="fms-panel"><h3>Budget Validation</h3>
@if($validation)
<p>Budget: <strong>{{ $validation['budget']->request_id }}</strong> — {{ $validation['budget']->budget_name }} ({{ $validation['budget']->academic_year }})</p>
<p>Approved: <strong>P{{ number_format($validation['approved'],2) }}</strong> | Allocated: <strong>P{{ number_format($validation['allocated'],2) }}</strong></p>
<p>Committed: <strong>P{{ number_format($validation['committed'],2) }}</strong> | Utilized: <strong>P{{ number_format($validation['utilized'],2) }}</strong> | Available: <strong>P{{ number_format($validation['available'],2) }}</strong></p>
<p>Requested: <strong>P{{ number_format($record->amount,2) }}</strong>
@if($validation['valid'])<span class="status st-green">Budget Validated</span>@else<span class="status st-red">Budget Insufficient</span>@endif</p>
@if($validation['fund'])<p style="color:#64748b;font-size:.85rem;">Fund {{ $validation['fund']->fund_name }} — available P{{ number_format((float) $validation['fund']->available_amount,2) }}</p>@endif
<p style="color:#64748b;font-size:.85rem;">Fund Source: <strong>{{ $validation['budget']->funding_source ?: '—' }}</strong></p>
@elseif($record->budget_plan_id)<div class="alert alert-warning">Linked budget is not approved/active — validation unavailable.</div>
@else<p style="color:#64748b;">No linked budget — link an approved budget to validate financial impact.</p>@endif
</div>
<div class="fms-panel"><h3>Financial Summary</h3>
<table class="fms-table"><tbody>
<tr><td style="color:#64748b;">Requested Amount</td><td><strong>P{{ number_format($record->amount,2) }}</strong></td></tr>
<tr><td style="color:#64748b;">Accountant Recommended</td><td><strong>{{ isset($record->metadata['recommended_amount']) ? 'P'.number_format((float) $record->metadata['recommended_amount'],2) : '—' }}</strong></td></tr>
<tr><td style="color:#64748b;">Admin Approved Amount</td><td><strong>{{ in_array($record->status, ['approved','completed']) ? 'P'.number_format($record->amount,2) : '—' }}</strong></td></tr>
<tr><td style="color:#64748b;">Actual Amount</td><td>{{ $record->status === 'completed' ? 'P'.number_format($record->amount,2) : '—' }}</td></tr>
</tbody></table>
@if(!empty($record->metadata['financial_remarks']))<p style="margin-top:8px;"><strong>Accountant Remarks:</strong> {{ $record->metadata['financial_remarks'] }}</p>@endif
</div>
@if($record->payable)
<div class="fms-panel"><h3>Expense &amp; Disbursement Fulfillment (read-only)</h3>
<p>Payable: <strong>{{ $record->payable->ap_number }}</strong> ({{ $record->payable->invoice_number }}) | Disbursement: <strong>{{ $record->payable->disbursement->reference_number ?? '—' }}</strong>@if($record->payable->disbursement && $record->payable->disbursement->processing_stage) — {{ ucwords(strtolower($record->payable->disbursement->processing_stage)) }}@endif</p>
<p>Approved (ceiling): <strong>P{{ number_format($record->approved_amount ?? $record->payable->amount,2) }}</strong> | Actual: <strong>{{ $record->actual_amount ? 'P'.number_format($record->actual_amount,2) : '—' }}</strong> | Disbursed: <strong>P{{ number_format($record->payable->amount_paid,2) }}</strong></p>
<p style="color:#64748b;font-size:.85rem;">Financial processing belongs to the Accountant — approval only authorizes the budget use.</p>
</div>
@endif
<div class="fms-panel"><h3>Status Timeline</h3>
<table class="fms-table"><tbody>
<tr><td>Received</td><td>{{ $record->received_at?->format('M d, Y h:i A') ?? $record->created_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td>Financial Review</td><td>{{ $record->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td>Admin Approval</td><td>{{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td>Completed</td><td>{{ $record->completed_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
</tbody></table>
</div>
<div class="fms-panel"><h3>Review — Approve / Return / Reject (same record)</h3>
@if(in_array($record->status, ['submitted','under_review']))
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:stretch;">
<form method="POST" action="{{ route('admin.financial-requests.approve', $record) }}" style="display:flex;flex-direction:column;gap:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;">@csrf<label style="font-size:.8rem;font-weight:600;">Approval remarks <span style="color:#64748b;font-weight:400;">(optional, max 60)</span></label><textarea name="admin_remarks" class="form-control remark-input" rows="2" maxlength="60" placeholder="Approval remarks (optional)" style="resize:none;"></textarea><small class="summary-desc"><span class="remark-count">0</span>/60</small><button class="btn btn-success" style="margin-top:auto;width:100%;" type="submit">Approve</button></form>
<form method="POST" action="{{ route('admin.financial-requests.return', $record) }}" style="display:flex;flex-direction:column;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">@csrf<label style="font-size:.8rem;font-weight:600;">Revision notes <span class="required">*</span> <span style="color:#64748b;font-weight:400;">(max 60)</span></label><textarea name="admin_remarks" class="form-control remark-input" rows="2" maxlength="60" placeholder="What needs revision? (required)" required style="resize:none;"></textarea><small class="summary-desc"><span class="remark-count">0</span>/60</small><button class="btn btn-secondary" style="margin-top:auto;width:100%;" type="submit">Return for Revision</button></form>
<form method="POST" action="{{ route('admin.financial-requests.reject', $record) }}" style="display:flex;flex-direction:column;gap:8px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;">@csrf<label style="font-size:.8rem;font-weight:600;">Rejection reason <span class="required">*</span> <span style="color:#64748b;font-weight:400;">(max 60)</span></label><textarea name="rejection_reason" class="form-control remark-input" rows="2" maxlength="60" placeholder="Rejection reason (required)" required style="resize:none;"></textarea><small class="summary-desc"><span class="remark-count">0</span>/60</small><button class="btn btn-danger" style="margin-top:auto;width:100%;" type="submit">Reject</button></form>
</div>
<script>
document.querySelectorAll('.remark-input').forEach((el) => {
    const count = el.parentElement.querySelector('.remark-count');
    const update = () => { if (count) count.textContent = el.value.length; };
    el.addEventListener('input', update); update();
});
</script>
@else<p style="color:#64748b;">Decision: {{ $record->admin_decision ?? $record->status }} by {{ $record->decider->name ?? '—' }} on {{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }}.</p>@endif
</div>
<div class="fms-panel">
<div class="filter-tabs" style="margin-bottom:12px;">
<a href="#tab-approvals" class="tab active" data-ftab="ftab-approvals">Approvals</a>
<a href="#tab-documents" class="tab" data-ftab="ftab-documents">Documents</a>
<a href="#tab-audit" class="tab" data-ftab="ftab-audit">Audit Trail</a>
<a href="#tab-related" class="tab" data-ftab="ftab-related">Related Transactions</a>
</div>
<div id="ftab-approvals"><table class="fms-table"><tbody>
<tr><td style="color:#64748b;">Reviewed By</td><td>{{ $record->reviewed_by ? (\App\Models\User::find($record->reviewed_by)?->name ?? '—') : '—' }} | {{ $record->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>
<tr><td style="color:#64748b;">Decided By</td><td>{{ $record->decider->name ?? '—' }} | {{ $record->decided_at?->format('M d, Y h:i A') ?? '—' }} | {{ $record->admin_decision ?? $record->status }}</td></tr>
</tbody></table></div>
<div id="ftab-documents" style="display:none;">@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View / Download</a></p>@else<p style="color:#64748b;">No supporting documents.</p>@endif</div>
<div id="ftab-audit" style="display:none;"><table class="fms-table"><thead><tr><th>Date</th><th>User</th><th>Action</th></tr></thead><tbody>
@forelse($history ?? [] as $h)<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }}</td><td>{{ $h->action }} — {{ $h->description }}</td></tr>@empty<tr><td colspan="3">No history</td></tr>@endforelse
</tbody></table></div>
<div id="ftab-related" style="display:none;">
@php $relAps = \App\Models\AccountsPayable::where('financial_request_id', $record->id)->get(); @endphp
@if($relAps->count())<table class="fms-table"><thead><tr><th>AP No.</th><th>Vendor</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
@foreach($relAps as $ap)<tr><td>{{ $ap->ap_number }}</td><td>{{ $ap->vendor }}</td><td style="text-align:right;">P{{ number_format($ap->amount,2) }}</td><td>{{ $ap->payment_stage }}</td></tr>@endforeach
</tbody></table>@else<p style="color:#64748b;">No linked payables yet.</p>@endif
</div>
</div>
<script>
document.querySelectorAll('[data-ftab]').forEach((t) => t.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('[data-ftab]').forEach((x) => x.classList.remove('active'));
    t.classList.add('active');
    ['ftab-approvals', 'ftab-documents', 'ftab-audit', 'ftab-related'].forEach((id) => {
        document.getElementById(id).style.display = id === t.getAttribute('data-ftab') ? '' : 'none';
    });
}));
</script>
</div>
</div>
@endsection
