@extends('layouts.admin')
@section('title', 'Expense Details')
@section('content')
<div class="page-header">
    <h2>Expense {{ $record->reference_number }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.expenses.edit', $record) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.expenses.index') }}">Back to List</a>
    </div>
</div>
<div class="fms-panel">
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Category</td><td>{{ $record->expense_category }}</td></tr>
        <tr><td style="color:#64748b;">Department</td><td>{{ $record->department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Payee</td><td>{{ $record->payee }}</td></tr>
        <tr><td style="color:#64748b;">Amount</td><td><strong>P{{ number_format($record->amount, 2) }}</strong></td></tr>
        <tr><td style="color:#64748b;">Date</td><td>{{ $record->expense_date }}</td></tr>
        <tr><td style="color:#64748b;">Approval status</td><td><span class="status">{{ ucfirst($record->approval_status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Payment status</td><td><span class="status">{{ ucfirst($record->payment_status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Description</td><td>{{ $record->description ?? '—' }}</td></tr>
    </tbody></table>
    <div class="form-actions">
        <form method="POST" action="{{ route('admin.expenses.approve', $record) }}">@csrf<button class="btn btn-success" type="submit" onclick="return confirm('Approve this expense?');">Approve</button></form>
        <form method="POST" action="{{ route('admin.expenses.reject', $record) }}">@csrf<button class="btn btn-danger" type="submit" onclick="return confirm('Reject this expense?');">Reject</button></form>
        <form method="POST" action="{{ route('admin.expenses.pay', $record) }}">@csrf<button class="btn btn-success" type="submit" onclick="return confirm('Mark this expense paid?');">Mark Paid</button></form>
    </div>
    <form method="POST" action="{{ route('admin.expenses.destroy', $record) }}" onsubmit="return confirm('Delete this expense?');" style="margin-top:12px;">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete Expense</button></form>
</div>
@endsection
