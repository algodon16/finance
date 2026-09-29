@extends('layouts.app')
@section('title', 'Revenue Transaction Details')
@section('content')
<div class="page-header"><div><h2>Revenue Transaction #{{ $record->id }}</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->status==='pending'?'draft':($record->status==='under_review'?'submitted':$record->status)]) | Verification: {{ $record->verification_status ?? 'pending' }} | Same record as Admin → Revenue Management.</p></div><a href="{{ route('accountant.revenue.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->rejection_reason)<div class="alert alert-error"><strong>Rejection reason:</strong> {{ $record->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $record->admin_remarks ?? '—' }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Transaction</h3>
<p><strong>Student:</strong> {{ $record->student->full_name ?? 'N/A' }} ({{ $record->student->student_number ?? '—' }})</p>
<p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }} | <strong>Method:</strong> {{ ucfirst(str_replace('_',' ',$record->payment_method)) }}</p>
<p><strong>Date:</strong> {{ optional($record->payment_date)->format('M d, Y') }} | <strong>Category:</strong> {{ $record->fee_category ?? '—' }}</p>
<p><strong>Reference:</strong> {{ $record->reference_number ?? '—' }}</p>
<p><strong>Description:</strong> {{ $record->description ?? '—' }}</p>
@if($record->accountLedgerEntries->count())<h3 style="margin-top:12px;">Ledger Entries</h3><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference</th><th style="text-align:right;">Credit</th></tr></thead><tbody>@foreach($record->accountLedgerEntries as $e)<tr><td>{{ $e->transaction_date }}</td><td>{{ $e->reference_number }}</td><td style="text-align:right;">₱{{ number_format($e->credit,2) }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
<div class="dashboard-card"><h3>Verification &amp; Submission</h3>
<p>Verified: {{ $record->verified_at?->format('M d, Y h:i A') ?? '—' }} | Reviewed by: {{ $record->reviewer->name ?? '—' }}</p>
@if(!in_array($record->status,['approved','posted','cancelled']))
<form method="POST" action="{{ route('accountant.revenue.verify',$record) }}" style="margin-bottom:12px;">@csrf<input type="text" name="remarks" placeholder="Verification remarks (optional)" class="form-control" style="margin-bottom:8px;"><button class="btn btn-primary">Verify Payment Information</button></form>
<form method="POST" action="{{ route('accountant.revenue.rejectInvalid',$record) }}" style="margin-bottom:12px;">@csrf<input type="text" name="rejection_reason" placeholder="Rejection reason (required)" class="form-control" required style="margin-bottom:8px;"><button class="btn btn-danger">Reject Invalid Information</button></form>
@endif
<div style="display:flex;gap:8px;flex-wrap:wrap;">
@if(in_array($record->status,['pending','rejected','cancelled']))<a href="{{ route('accountant.revenue.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a><form method="POST" action="{{ route('accountant.revenue.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Approval</button></form>@endif
@if($record->status==='under_review')<form method="POST" action="{{ route('accountant.revenue.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
