@extends('layouts.admin')
@section('title', 'Payable Details')
@section('content')
<div class="page-header">
    <h2>Invoice {{ $record->invoice_number }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.payables.edit', $record) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.payables.index') }}">Back to List</a>
    </div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Invoice Amount</h4><p class="val">P{{ number_format($record->amount, 2) }}</p></div>
    <div class="fms-stat"><h4>Amount Paid</h4><p class="val">P{{ number_format($record->amount_paid, 2) }}</p></div>
    <div class="fms-stat"><h4>Remaining Balance</h4><p class="val">P{{ number_format($record->remaining_balance, 2) }}</p></div>
    <div class="fms-stat"><h4>Status</h4><p class="val" style="font-size:1.1rem;">{{ $record->derived_status }}</p></div>
</div>

<div class="fms-panel">
    <h3>Approval — same record (submitted → approved / rejected)</h3>
    <p style="color:#64748b;">Approval: <span class="status">{{ \App\Services\WorkflowService::label($record->approval_status ?? 'draft') }}</span> | Prepared by {{ $record->creator->name ?? '—' }} | Submitted {{ $record->submitted_at?->format('M d, Y h:i A') ?? '—' }} | Revision {{ $record->revision_number ?? 0 }}</p>
    @if($record->rejection_reason)<p><strong>Rejection reason:</strong> {{ $record->rejection_reason }}</p>@endif
    @if($record->admin_remarks)<p><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</p>@endif
    @if($record->expense)<p><strong>Linked expense:</strong> {{ $record->expense->reference_number }} (P{{ number_format($record->expense->amount,2) }})</p>@endif
    @if($record->disbursement)<p><strong>Auto Disbursement:</strong> <a href="{{ route('admin.expenses.show', $record->disbursement) }}">{{ $record->disbursement->reference_number }}</a> (P{{ number_format($record->disbursement->amount,2) }} — {{ \App\Services\WorkflowService::label($record->disbursement->payment_status) }})</p>
    @elseif(($record->approval_status ?? '') === 'approved')
    <form method="POST" action="{{ route('admin.payables.disbursement', $record) }}" style="margin-top:8px;">@csrf<button class="btn btn-secondary" type="submit">Sync to Disbursement (approved before auto-forward existed)</button></form>
    @endif
    @if(in_array($record->approval_status ?? 'draft', ['submitted','under_review']))
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
    <form method="POST" action="{{ route('admin.payables.approve', $record) }}">@csrf<textarea name="admin_remarks" class="form-control" rows="2" placeholder="Approval remarks (optional)"></textarea><button class="btn btn-primary" style="margin-top:8px;" type="submit">Approve</button></form>
    <form method="POST" action="{{ route('admin.payables.reject', $record) }}">@csrf<input type="text" name="rejection_reason" class="form-control" placeholder="Rejection reason (required)" required><button class="btn btn-danger" style="margin-top:8px;" type="submit">Reject</button></form>
    </div>
    @endif
</div>

@if(!empty($history) && $history->count())
<div class="fms-panel">
    <h3>Approval History</h3>
    <table class="fms-table"><thead><tr><th>Date</th><th>User</th><th>Action</th></tr></thead><tbody>
    @foreach($history as $h)<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }}</td><td>{{ $h->action }} — {{ $h->description }}</td></tr>@endforeach
    </tbody></table>
</div>
@endif

<div class="fms-panel">
    <h3>Post a Payment</h3>
    <form method="POST" action="{{ route('admin.payables.pay', $record) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf
        <div><label style="font-size:0.8rem;">Amount (PHP) *</label><br><input type="number" step="0.01" min="0.01" max="{{ $record->remaining_balance }}" name="amount" class="form-control" required></div>
        <div><label style="font-size:0.8rem;">Payment Date *</label><br><input type="date" name="payment_date" class="form-control" value="{{ today()->toDateString() }}" required></div>
        <div><label style="font-size:0.8rem;">Method</label><br><input type="text" name="payment_method" class="form-control" placeholder="Cash / Bank"></div>
        <div><label style="font-size:0.8rem;">Reference</label><br><input type="text" name="reference_number" class="form-control"></div>
        <div><button class="btn btn-success" type="submit">Post Payment</button></div>
    </form>
</div>

<div class="fms-panel">
    <h3>Payment History</h3>
    <table class="fms-table">
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody>
        @forelse($record->payments as $p)
            <tr><td>{{ $p->payment_date }}</td><td>{{ $p->payment_method ?? '—' }}</td><td>{{ $p->reference_number ?? '—' }}</td><td style="text-align:right;">P{{ number_format($p->amount, 2) }}</td></tr>
        @empty
            <tr><td colspan="4" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
