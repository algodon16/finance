@extends('layouts.app')
@section('title', 'Expense / Disbursement Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->reference_number }} — {{ $record->payee }}</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->approval_status]) | {{ $record->origin_label }} | Payment: {{ \App\Services\WorkflowService::label($record->payment_status) }} | Rev {{ $record->revision_number ?? 0 }}</p></div><a href="{{ route('accountant.expenses.index') }}" class="btn btn-secondary">Back</a></div>

@if($record->related_payable_id)
<div class="alert alert-success">Auto-generated from {{ $record->sourcePayable->ap_number ?? 'AP' }} — same transaction, not a duplicate.</div>
@endif

<div class="two-col-grid">
<div class="dashboard-card"><h3>Transaction Summary</h3>
<p><strong>Expense/Disbursement ID:</strong> {{ $record->reference_number }}</p>
@if($record->sourcePayable)<p><strong>AP ID:</strong> <a href="{{ route('accountant.payables.show',$record->sourcePayable) }}">{{ $record->sourcePayable->ap_number }}</a> (Invoice {{ $record->sourcePayable->invoice_number }})</p>@endif
<p><strong>Vendor:</strong> {{ $record->payee }}</p>
<p><strong>Department:</strong> {{ $record->department ?? '—' }}</p>
<p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }}</p>
<p><strong>Status:</strong> @include('accountant.partials.status-badge',['status'=>$record->approval_status]) | {{ \App\Services\WorkflowService::label($record->payment_status) }}</p>
<p><strong>Description:</strong> {{ $record->description }}</p>
@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View document</a></p>@endif
</div>

<div class="dashboard-card"><h3>Source Information</h3>
@if($record->sourcePayable)
@php $ap = $record->sourcePayable; @endphp
<p><strong>Request:</strong> {{ $ap->financialRequest ? $ap->financialRequest->request_number.' ('.ucfirst(str_replace('_',' ',$ap->financialRequest->request_type)).')' : '—' }}</p>
<p><strong>Budget Plan:</strong> {{ $record->budgetPlan ? $record->budgetPlan->budget_name.' ('.$record->budgetPlan->request_id.')' : '—' }}</p>
<p><strong>Fund Allocation:</strong> {{ $record->allocation ? 'FA-'.$record->allocation->id.' — '.$record->allocation->allocated_to : '—' }}</p>
<p><strong>Invoice Date:</strong> {{ optional($ap->invoice_date)->format('M d, Y') }} | <strong>Due:</strong> {{ optional($ap->due_date)->format('M d, Y') }} | <strong>Terms:</strong> {{ $ap->payment_terms ?? '—' }}</p>
@else
<p><strong>Budget:</strong> {{ $record->budgetPlan->budget_name ?? '—' }} | <strong>Allocation:</strong> {{ $record->allocation->allocated_to ?? '—' }} | <strong>Fund:</strong> {{ $record->fund->fund_name ?? $record->fund_source ?? '—' }}</p>
<p class="summary-desc">Standalone manual proposal — not linked to any AP.</p>
@endif
</div>
</div>

<div class="two-col-grid" style="margin-top:16px;">
<div class="dashboard-card"><h3>Payment Information</h3>
<p><strong>Payment Status:</strong> {{ \App\Services\WorkflowService::label($record->payment_status) }}</p>
<p><strong>Payment Date:</strong> {{ optional($record->payment_date)->format('M d, Y') ?? '—' }}</p>
<p><strong>Payment Method:</strong> {{ $record->payment_method ?? '—' }}</p>
<p><strong>Reference Number:</strong> {{ $record->payment_reference ?? '—' }}</p>
@if($record->proof_of_payment)<p><a href="{{ asset('storage/'.$record->proof_of_payment) }}" target="_blank" class="btn btn-sm btn-secondary">View Proof of Payment</a></p>@endif
@if($record->relatedPayable)<p><strong>Linked Payable:</strong> <a href="{{ route('accountant.payables.show',$record->relatedPayable) }}">{{ $record->relatedPayable->invoice_number }}</a></p>@endif
</div>

<div class="dashboard-card"><h3>Budget Impact</h3>
@if($impact)
<p>Approved Budget: <strong>₱{{ number_format($impact['approved'],2) }}</strong></p>
<p>Previously Used: <strong>₱{{ number_format($impact['used'],2) }}</strong></p>
<p>Remaining Budget: <strong>₱{{ number_format($impact['remaining'],2) }}</strong></p>
<p>Current Expense: <strong>₱{{ number_format($impact['proposal'],2) }}</strong></p>
<p>Remaining After: <strong style="color:{{ $impact['after'] < 0 ? '#b91c1c' : '#047857' }};">₱{{ number_format($impact['after'],2) }}</strong></p>
@else<p class="summary-desc">No linked budget — impact computed on submit.</p>@endif
</div>
</div>

@if($record->sourcePayable && $record->sourcePayable->financialRequest)
@php $fr = $record->sourcePayable->financialRequest; @endphp
<div class="dashboard-card" style="margin-top:16px;"><div class="card-head-row"><h3>Financial Processing — {{ $record->processing_stage ? ucwords(strtolower($record->processing_stage)) : 'For Processing' }}</h3><a href="{{ route('accountant.payables.show',$record->sourcePayable) }}" class="view-all-link">Open Payable</a></div>
<div class="two-col-grid">
<div><h4 style="font-size:.85rem;color:#64748b;">REQUEST INFORMATION</h4>
<p><strong>Request ID:</strong> {{ $fr->source_request_id ?: $fr->request_number }} <span class="summary-desc">({{ $fr->request_number }})</span></p>
<p><strong>Source:</strong> {{ $fr->source_label }}</p>
<p><strong>Type:</strong> {{ ucfirst(str_replace('_',' ',$fr->request_type)) }} | <strong>Department:</strong> {{ $fr->department }}</p>
<p><strong>Purpose:</strong> {{ $fr->description }}</p>
<p><strong>Original Requested:</strong> ₱{{ number_format($fr->amount,2) }}</p></div>
<div><h4 style="font-size:.85rem;color:#64748b;">APPROVAL INFORMATION</h4>
<p><strong>Accountant Recommended:</strong> {{ $fr->metadata['recommended_amount'] ?? null ? '₱'.number_format((float) $fr->metadata['recommended_amount'],2) : '—' }}</p>
<p><strong>Admin Approved (ceiling):</strong> ₱{{ number_format($fr->approved_amount ?? $record->sourcePayable->amount,2) }}</p>
<p><strong>Approval Date:</strong> {{ optional($fr->decided_at)->format('M d, Y h:i A') ?? '—' }} | <strong>By:</strong> {{ $fr->decider->name ?? '—' }}</p>
<h4 style="font-size:.85rem;color:#64748b;margin-top:8px;">BUDGET INFORMATION</h4>
<p><strong>Budget Ref:</strong> {{ $record->budgetPlan ? $record->budgetPlan->request_id.' — '.$record->budgetPlan->budget_name : '—' }}</p>
<p><strong>Fund Source:</strong> {{ $record->budgetPlan->funding_source ?? $record->fund->fund_name ?? $record->fund_source ?? '—' }}</p>
<p><strong>Allocated:</strong> ₱{{ number_format($record->budgetPlan->allocated_amount ?? 0,2) }} | <strong>Utilized:</strong> ₱{{ number_format($record->budgetPlan->utilized_amount ?? 0,2) }} | <strong>Available:</strong> ₱{{ number_format($record->budgetPlan->remaining_amount ?? 0,2) }}</p></div>
</div>
<div style="margin-top:12px;"><h4 style="font-size:.85rem;color:#64748b;">ACTUAL FINANCIAL INFORMATION</h4>
<p><strong>Actual Expense:</strong> {{ $fr->actual_amount ? '₱'.number_format($fr->actual_amount,2).' ('.optional(\Carbon\Carbon::parse($fr->metadata['actual_date'] ?? null))->format('M d, Y') .')' : '— not yet recorded (approved ceiling is not the actual)' }}</p>
<p><strong>Disbursed:</strong> ₱{{ number_format($record->sourcePayable->amount_paid,2) }} of ₱{{ number_format($fr->actual_amount ?? $record->sourcePayable->amount,2) }} | <strong>Method:</strong> {{ $record->payment_method ?? '—' }} | <strong>Reference:</strong> {{ $record->payment_reference ?? '—' }}</p>
@if($fr->metadata['actual_remarks'] ?? null)<p><strong>Remarks:</strong> {{ $fr->metadata['actual_remarks'] }}</p>@endif
@if($fr->status === 'completed')<p><span class="badge badge-green">Completed</span> <span class="summary-desc">Actual posted to budget utilization. Unused authorization released.</span></p>@endif</div>
@if($fr->status === 'approved' && $record->payment_status !== 'paid')
<div class="two-col-grid" style="margin-top:12px;">
<div><h4 style="font-size:.85rem;">Record Actual Expense</h4>
<form method="POST" action="{{ route('accountant.expenses.record-actual',$record) }}" enctype="multipart/form-data">@csrf
<div class="form-group"><label>Actual Amount (₱) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" max="{{ (float) $record->sourcePayable->amount }}" name="actual_amount" class="form-control" value="{{ old('actual_amount', $fr->actual_amount ?? '') }}" required></div>
<div class="form-group"><label>Transaction Date <span class="required">*</span></label><input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', $fr->metadata['actual_date'] ?? today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
<div class="form-group"><label>Payee / Supplier</label><input type="text" name="vendor" class="form-control" value="{{ old('vendor', $record->payee) }}" maxlength="255"></div>
<div class="form-group"><label>Payment Method <span class="required">*</span></label><select name="payment_method" class="form-control" required><option value="">Select</option>@foreach(\App\Models\Expense::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select></div>
<div class="form-group"><label>Payment Reference</label><input type="text" name="payment_reference" class="form-control" maxlength="100"></div>
<div class="form-group"><label>Supporting Document</label><input type="file" name="supporting_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
<div class="form-group"><label>Remarks (max 60)</label><input type="text" name="remarks" class="form-control" maxlength="60" value="{{ old('remarks') }}"></div>
<button class="btn btn-primary">Save Actual</button></form></div>
<div><h4 style="font-size:.85rem;">Record Disbursement</h4>
<form method="POST" action="{{ route('accountant.payables.pay',$record->sourcePayable) }}">@csrf
<div class="form-group"><label>Amount (₱) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
<div class="form-group"><label>Disbursement Date <span class="required">*</span></label><input type="date" name="payment_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
<div class="form-group"><label>Payment Method</label><select name="payment_method" class="form-control"><option value="">—</option>@foreach(\App\Models\Expense::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select></div>
<div class="form-group"><label>Payment Reference</label><input type="text" name="reference_number" class="form-control" maxlength="100"></div>
<div class="form-group"><label>Remarks</label><input type="text" name="remarks" class="form-control" maxlength="1000"></div>
<button class="btn btn-secondary">Record Disbursement</button></form>
<form method="POST" action="{{ route('accountant.payables.complete',$record->sourcePayable) }}" style="margin-top:12px;" onsubmit="return confirm('Mark this transaction COMPLETED? Actual will post to budget utilization.')">@csrf<button class="btn btn-primary">Mark Completed</button></form>
<p class="summary-desc">Completion requires recorded actual, full disbursement, and supporting documents.</p></div>
</div>
@endif
@endif

@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@if(!empty($autoHistory) && count($autoHistory))
<div class="dashboard-card" style="margin-top:16px;"><h3>Linked AP Timeline</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
@foreach($autoHistory as $h)<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }}</td><td><span class="badge badge-gray">{{ $h->action }}</span></td><td>{{ $h->description }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@endsection