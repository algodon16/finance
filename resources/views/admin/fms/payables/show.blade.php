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
