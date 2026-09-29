@extends('layouts.admin')
@section('title', 'Expense / Disbursement Details')
@section('content')
<div class="page-header">
    <h2>{{ $record->reference_number }} @if($record->related_payable_id)<span class="status st-green">Auto from AP</span>@endif</h2>
    <div style="display:flex;gap:8px;">
        @if(!$record->related_payable_id)<a class="btn btn-secondary" href="{{ route('admin.expenses.edit', $record) }}">Edit</a>@endif
        <a class="btn btn-secondary" href="{{ route('admin.expenses.index') }}">Back to List</a>
    </div>
</div>
@if($record->related_payable_id)<p style="color:#047857;font-size:0.85rem;">Auto-generated from {{ $record->sourcePayable->ap_number ?? 'AP' }} — the same transaction, not a duplicate. Edit the AP instead of this record.</p>@endif
<div class="fms-panel">
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Expense / Disbursement ID</td><td><strong>{{ $record->reference_number }}</strong></td></tr>
        @if($record->sourcePayable)
        <tr><td style="color:#64748b;">AP ID</td><td><a href="{{ route('admin.payables.show', $record->sourcePayable) }}"><strong>{{ $record->sourcePayable->ap_number }}</strong></a> (Invoice {{ $record->sourcePayable->invoice_number }})</td></tr>
        <tr><td style="color:#64748b;">Vendor</td><td>{{ $record->payee }}</td></tr>
        <tr><td style="color:#64748b;">Department</td><td>{{ $record->department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount, 2) }}</strong></td></tr>
        <tr><td style="color:#64748b;">Status</td><td><span class="status">{{ \App\Services\WorkflowService::label($record->approval_status) }}</span> | <span class="status">{{ \App\Services\WorkflowService::label($record->payment_status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Request ID</td><td>{{ $record->sourcePayable->expense ? $record->sourcePayable->expense->reference_number.' (Expense Proposal)' : ($record->sourcePayable->financialRequest ? $record->sourcePayable->financialRequest->request_number : '—') }}</td></tr>
        <tr><td style="color:#64748b;">Budget Plan</td><td>{{ $record->budgetPlan ? $record->budgetPlan->budget_name.' (BP-2026-'.str_pad($record->budgetPlan->id,4,'0',STR_PAD_LEFT).')' : '—' }}</td></tr>
        <tr><td style="color:#64748b;">Fund Allocation</td><td>{{ $record->allocation ? 'FA-'.$record->allocation->id.' — '.$record->allocation->allocated_to : '—' }}</td></tr>
        <tr><td style="color:#64748b;">Invoice Date / Due / Terms</td><td>{{ optional($record->sourcePayable->invoice_date)->format('M d, Y') }} / {{ optional($record->sourcePayable->due_date)->format('M d, Y') }} / {{ $record->sourcePayable->payment_terms ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Invoice Attachment</td><td>@if($record->sourcePayable->supporting_document)<a href="{{ asset('storage/'.$record->sourcePayable->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View / Download</a>@else — @endif</td></tr>
        @else
        <tr><td style="color:#64748b;">Category</td><td>{{ $record->expense_category }}</td></tr>
        <tr><td style="color:#64748b;">Department</td><td>{{ $record->department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Payee</td><td>{{ $record->payee }}</td></tr>
        <tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount, 2) }}</strong></td></tr>
        <tr><td style="color:#64748b;">Date</td><td>{{ $record->expense_date }}</td></tr>
        <tr><td style="color:#64748b;">Approval status</td><td><span class="status">{{ \App\Services\WorkflowService::label($record->approval_status) }}</span> | Prepared by {{ $record->creator->name ?? '—' }} | Submitted {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }} | Revision {{ $record->revision_number ?? 0 }}</td></tr>
        <tr><td style="color:#64748b;">Budget / Allocation</td><td>{{ $record->budgetPlan->budget_name ?? '—' }} / {{ $record->allocation->allocated_to ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Payment status</td><td><span class="status">{{ \App\Services\WorkflowService::label($record->payment_status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Description</td><td>{{ $record->description ?? '—' }}</td></tr>
        @endif
        <tr><td style="color:#64748b;">Payment Date</td><td>{{ optional($record->payment_date)->format('M d, Y') ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Payment Method</td><td>{{ $record->payment_method ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Payment Reference</td><td>{{ $record->payment_reference ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Proof of Payment</td><td>@if($record->proof_of_payment)<a href="{{ asset('storage/'.$record->proof_of_payment) }}" target="_blank" class="btn btn-sm btn-secondary">View Proof</a>@else — @endif</td></tr>
        @if($record->rejection_reason)<tr><td style="color:#64748b;">Rejection Reason</td><td>{{ $record->rejection_reason }}</td></tr>@endif
        @if($record->admin_remarks)<tr><td style="color:#64748b;">Admin Remarks</td><td>{{ $record->admin_remarks }}</td></tr>@endif
    </tbody></table>
    @if(in_array($record->approval_status, ['submitted','under_review']))
    <div class="form-actions">
        <form method="POST" action="{{ route('admin.expenses.approve', $record) }}">@csrf<textarea name="admin_remarks" class="form-control" rows="2" placeholder="Approval remarks (optional)"></textarea><button class="btn btn-success" type="submit" onclick="return confirm('Approve this expense?');" style="margin-top:8px;">Approve</button></form>
        <form method="POST" action="{{ route('admin.expenses.reject', $record) }}">@csrf<input type="text" name="rejection_reason" class="form-control" placeholder="Rejection reason (required)" required><button class="btn btn-danger" type="submit" onclick="return confirm('Reject this expense?');" style="margin-top:8px;">Reject</button></form>
    </div>
    @elseif($record->approval_status === 'approved' && $record->payment_status !== 'paid')
    <div class="form-actions" style="margin-top:12px;">
        <h4>Process Payment / Disbursement @if($record->sourcePayable)(settles remaining ₱{{ number_format($record->sourcePayable->remaining_balance, 2) }} on {{ $record->sourcePayable->ap_number }})@endif</h4>
        @if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <form method="POST" action="{{ route('admin.expenses.pay', $record) }}" enctype="multipart/form-data" style="display:grid;gap:8px;max-width:520px;">
            @csrf
            <div><label>Payment Date *</label><input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></div>
            <div><label>Payment Method *</label><select name="payment_method" class="form-control" required><option value="">— Select —</option>@foreach(\App\Models\Expense::PAYMENT_METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select></div>
            <div><label>Payment Reference Number</label><input type="text" name="payment_reference" class="form-control" maxlength="100" placeholder="Check / transfer ref…"></div>
            <div><label>Proof of Payment (PDF/JPG/PNG, max 5 MB)</label><input type="file" name="proof_of_payment" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
            <div><button class="btn btn-success" type="submit" onclick="return confirm('Settle this disbursement? Linked AP becomes Paid.');">Settle Payment</button></div>
        </form>
    </div>
    @endif
    @if(!$record->related_payable_id)
    <form method="POST" action="{{ route('admin.expenses.destroy', $record) }}" onsubmit="return confirm('Delete this expense?');" style="margin-top:12px;">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete Expense</button></form>
    @endif
</div>
@endsection
