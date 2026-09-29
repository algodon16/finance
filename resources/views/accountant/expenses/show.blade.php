@extends('layouts.app')
@section('title', 'Expense / Disbursement Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->reference_number }} — {{ $record->payee }}</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->approval_status]) | {{ $record->origin_label }} | Payment: {{ \App\Services\WorkflowService::label($record->payment_status) }} | Rev {{ $record->revision_number ?? 0 }}</p></div><a href="{{ route('accountant.expenses.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->rejection_reason)<div class="alert alert-error"><strong>Admin decision:</strong> {{ $record->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</div>@endif
@if($record->related_payable_id)<div class="alert alert-success">Auto-generated from {{ $record->sourcePayable->ap_number ?? 'AP' }} — the same transaction, not a duplicate. It follows its AP and cannot be edited here.</div>@endif
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
<p><strong>Request:</strong> {{ $ap->expense ? $ap->expense->reference_number.' (Expense Proposal)' : ($ap->financialRequest ? $ap->financialRequest->request_number.' ('.ucfirst(str_replace('_',' ',$ap->financialRequest->request_type)).')' : '—') }}</p>
<p><strong>Budget Plan:</strong> {{ $record->budgetPlan ? $record->budgetPlan->budget_name.' (BP-2026-'.str_pad($record->budgetPlan->id,4,'0',STR_PAD_LEFT).')' : '—' }}</p>
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
<div class="dashboard-card"><h3>Budget Impact Panel</h3>
@if($impact)
<p>Approved Budget: <strong>₱{{ number_format($impact['approved'],2) }}</strong></p>
<p>Allocated Amount: <strong>₱{{ number_format($impact['allocated'],2) }}</strong></p>
<p>Previously Used: <strong>₱{{ number_format($impact['used'],2) }}</strong></p>
<p>Remaining Budget: <strong>₱{{ number_format($impact['remaining'],2) }}</strong></p>
<p>Current Record: <strong>₱{{ number_format($impact['proposal'],2) }}</strong></p>
<p>Remaining After: <strong style="color:{{ $impact['after'] < 0 ? '#b91c1c' : '#047857' }};">₱{{ number_format($impact['after'],2) }}</strong></p>
@else<p class="summary-desc">No linked budget — impact computed on submit via server validation.</p>@endif
@if(!$record->related_payable_id)
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if(in_array($record->approval_status,['draft','for_revision','revision','rejected','cancelled']))<a href="{{ route('accountant.expenses.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a><form method="POST" action="{{ route('accountant.expenses.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Admin Approval</button></form>@endif
@if(in_array($record->approval_status,['submitted','under_review']))<form method="POST" action="{{ route('accountant.expenses.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div>
@endif
</div>
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@if(!empty($autoHistory) && count($autoHistory))
<div class="dashboard-card" style="margin-top:16px;"><h3>Linked AP Timeline</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
@foreach($autoHistory as $h)<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }}</td><td><span class="badge badge-gray">{{ $h->action }}</span></td><td>{{ $h->description }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@endsection
