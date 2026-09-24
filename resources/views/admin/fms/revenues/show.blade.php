@extends('layouts.admin')
@section('title', 'Revenue Transaction Details')
@section('content')
<div class="page-header">
    <h2>Revenue Transaction {{ $record->transaction_number ?? ('#'.$record->id) }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.revenues.edit', $record) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.revenues.index') }}">Back to List</a>
    </div>
</div>
<div class="fms-panel">
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Student</td><td>{{ $record->student->full_name ?? '—' }} ({{ $record->student->student_number ?? '—' }})</td></tr>
        <tr><td style="color:#64748b;">Payment date</td><td>{{ $record->payment_date }}</td></tr>
        <tr><td style="color:#64748b;">Method</td><td>{{ $record->payment_method }}</td></tr>
        <tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount, 2) }}</strong></td></tr>
        <tr><td style="color:#64748b;">Fee category</td><td>{{ $record->fee_category ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Reference number</td><td>{{ $record->reference_number ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Status</td><td><span class="status">{{ ucfirst($record->status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Verification status</td><td><span class="status">{{ ucfirst($record->verification_status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Description</td><td>{{ $record->description ?? '—' }}</td></tr>
    </tbody></table>
    <div class="form-actions">
        <form method="POST" action="{{ route('admin.revenues.verify', $record) }}">@csrf<button class="btn btn-success" type="submit" onclick="return confirm('Verify this payment?');">Verify</button></form>
        <form method="POST" action="{{ route('admin.revenues.reject', $record) }}">@csrf<button class="btn btn-danger" type="submit" onclick="return confirm('Reject this payment?');">Reject</button></form>
        <form method="POST" action="{{ route('admin.revenues.reconcile', $record) }}">@csrf<button class="btn btn-success" type="submit" onclick="return confirm('Mark this payment reconciled?');">Reconcile</button></form>
    </div>
</div>
@endsection
