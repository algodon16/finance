@extends('layouts.app')
@section('title', 'Payable Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->invoice_number }} — {{ $record->source_vendor }}</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->approval_status ?? 'draft']) | AP #{{ $record->id }} | Rev {{ $record->revision_number ?? 0 }}</p></div><a href="{{ route('accountant.payables.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->rejection_reason)<div class="alert alert-error"><strong>Admin decision:</strong> {{ $record->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Payable Summary</h3>
<p><strong>AP ID:</strong> {{ $record->ap_number }}</p>
@if($record->disbursement)<p><strong>Disbursement:</strong> <a href="{{ route('accountant.expenses.show',$record->disbursement) }}">{{ $record->disbursement->reference_number }}</a> ({{ \App\Services\WorkflowService::label($record->disbursement->payment_status) }}) — auto-generated, same transaction</p>@elseif(($record->approval_status ?? '')==='approved')<p class="summary-desc">Approved — disbursement record generates automatically.</p>@endif
<p><strong>Invoice Number:</strong> {{ $record->invoice_number }}</p>
<p><strong>Vendor:</strong> {{ $record->source_vendor }}</p>
<p><strong>Invoice Date:</strong> {{ optional($record->invoice_date)->format('M d, Y') }}</p>
<p><strong>Due Date:</strong> {{ optional($record->due_date)->format('M d, Y') }}</p>
<p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }} | <strong>Paid:</strong> ₱{{ number_format($record->amount_paid,2) }} | <strong>Remaining:</strong> ₱{{ number_format($record->remaining_balance,2) }}</p>
<p><strong>Payment Terms:</strong> {{ $record->payment_terms ?? '—' }}</p>
<p><strong>Status:</strong> @include('accountant.partials.status-badge',['status'=>$record->approval_status ?? 'draft']) | Payment: @include('accountant.partials.status-badge',['status'=>$record->payment_status]) ({{ $record->derived_status }})</p>
</div>
<div class="dashboard-card"><h3>Source Transaction</h3>
@if($record->expense)
<p><strong>Request ID:</strong> <a href="{{ route('accountant.expenses.show',$record->expense) }}">{{ $record->expense->reference_number }}</a> (Expense Proposal)</p>
<p><strong>Request Name:</strong> {{ $record->expense->description ?? $record->expense->expense_category }}</p>
<p><strong>Department:</strong> {{ $record->expense->department ?? '—' }}</p>
<p><strong>Budget Reference:</strong> {{ $record->budgetPlan ? 'BP-2026-'.str_pad($record->budgetPlan->id,4,'0',STR_PAD_LEFT).' — '.$record->budgetPlan->budget_name : '—' }}</p>
<p><strong>Fund Allocation Reference:</strong> {{ $record->allocation ? 'FA-'.$record->allocation->id.' — '.$record->allocation->allocated_to : '—' }}</p>
@elseif($record->financialRequest)
<p><strong>Request ID:</strong> <a href="{{ route('accountant.financial-requests.show',$record->financialRequest) }}">{{ $record->financialRequest->request_number }}</a> ({{ ucfirst(str_replace('_',' ',$record->financialRequest->request_type)) }})</p>
<p><strong>Request Name:</strong> {{ $record->financialRequest->description }}</p>
<p><strong>Department:</strong> {{ $record->financialRequest->department ?? '—' }}</p>
<p><strong>Budget Reference:</strong> {{ $record->budgetPlan ? 'BP-2026-'.str_pad($record->budgetPlan->id,4,'0',STR_PAD_LEFT).' — '.$record->budgetPlan->budget_name : '—' }}</p>
<p><strong>Fund Allocation Reference:</strong> {{ $record->allocation ? 'FA-'.$record->allocation->id.' — '.$record->allocation->allocated_to : '—' }}</p>
@else
<p class="summary-desc">Legacy record — no linked source. Vendor: {{ $record->vendor }}. New payables always link a source.</p>
@endif
</div>
</div>
<div class="two-col-grid" style="margin-top:16px;">
<div class="dashboard-card"><h3>Supporting Document</h3>
@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View / Download Invoice</a></p>@else<p class="summary-desc">No attachment.</p>@endif
@if($record->remarks)<p><strong>Remarks:</strong> {{ $record->remarks }}</p>@endif
@if($record->payments->count())<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>@foreach($record->payments as $p)<tr><td>{{ optional($p->payment_date)->format('M d, Y') }}</td><td>₱{{ number_format($p->amount,2) }}</td><td>{{ $p->payment_method }}</td><td>{{ $p->reference_number }}</td></tr>@endforeach</tbody></table></div>@else<p class="summary-desc">No payments recorded. Unapproved payables cannot become paid.</p>@endif
</div>
<div class="dashboard-card"><h3>Actions</h3>
<p><strong>Created:</strong> {{ optional($record->created_at)->format('M d, Y h:i A') }}</p>
<p><strong>Submitted:</strong> {{ optional($record->submitted_at)->format('M d, Y h:i A') ?? '—' }}</p>
<p><strong>Approved / Rejected:</strong> {{ optional($record->approved_at)->format('M d, Y h:i A') ?? optional($record->reviewed_at)->format('M d, Y h:i A') ?? '—' }}</p>
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if(in_array($record->approval_status ?? 'draft',['draft','for_revision','revision','rejected','cancelled']))<a href="{{ route('accountant.payables.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a><form method="POST" action="{{ route('accountant.payables.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Admin Approval</button></form>@endif
@if(in_array($record->approval_status,['submitted','under_review']))<form method="POST" action="{{ route('accountant.payables.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
@if($record->financial_request_id && $record->financialRequest && ($record->approval_status ?? '') === 'approved')
@php $pFr = $record->financialRequest; $pExp = $record->disbursement; @endphp
<div class="dashboard-card" style="margin-top:16px;"><div class="card-head-row"><h3>Financial Processing — {{ $pExp && $pExp->processing_stage ? ucwords(strtolower($pExp->processing_stage)) : 'For Processing' }}</h3>@if($pExp)<a href="{{ route('accountant.expenses.show',$pExp) }}" class="view-all-link">Open Disbursement</a>@endif</div>
<p><strong>Request ID:</strong> {{ $pFr->source_request_id ?: $pFr->request_number }} | <strong>Approved (ceiling):</strong> ₱{{ number_format($pFr->approved_amount ?? $record->amount,2) }} | <strong>Actual:</strong> {{ $pFr->actual_amount ? '₱'.number_format($pFr->actual_amount,2) : '—' }} | <strong>Disbursed:</strong> ₱{{ number_format($record->amount_paid,2) }}</p>
@if($pFr->status === 'completed')<p><span class="badge badge-green">Completed</span> <span class="summary-desc">Actual posted to budget utilization.</span></p>
@else<div class="two-col-grid">
<div><h4 style="font-size:.85rem;">Record Disbursement</h4>
<form method="POST" action="{{ route('accountant.payables.pay',$record) }}">@csrf
<div class="form-group"><label>Amount (₱) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
<div class="form-group"><label>Disbursement Date <span class="required">*</span></label><input type="date" name="payment_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
<div class="form-group"><label>Payment Method</label><select name="payment_method" class="form-control"><option value="">—</option>@foreach(\App\Models\Expense::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select></div>
<div class="form-group"><label>Payment Reference</label><input type="text" name="reference_number" class="form-control" maxlength="100"></div>
<button class="btn btn-secondary">Record Disbursement</button></form></div>
<div><h4 style="font-size:.85rem;">Completion</h4><p class="summary-desc">Requires recorded actual, full disbursement, and supporting documents. Posts the ACTUAL to budget utilization and syncs the request to COMPLETED.</p>
<form method="POST" action="{{ route('accountant.payables.complete',$record) }}" onsubmit="return confirm('Mark this transaction COMPLETED?')">@csrf@if($errors->any())<div class="alert alert-error" style="margin-top:8px;margin-bottom:8px;">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif<button class="btn btn-primary">Mark Completed</button></form></div>
</div>@endif
</div>
@endif
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
