@extends('layouts.admin')
@section('title', 'Student Account Ledger')
@section('content')
<div class="page-header">
    <h2>Student Ledger - {{ $receivable->student->full_name ?? 'Unknown' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.receivables.index') }}">Back to List</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Assessed</h4><p class="val">P{{ number_format($receivable->total_charges, 2) }}</p></div>
    <div class="fms-stat"><h4>Amount Paid</h4><p class="val">P{{ number_format($paid, 2) }}</p></div>
    <div class="fms-stat"><h4>Remaining Balance</h4><p class="val">P{{ number_format($computedBalance, 2) }}</p><p class="sub">Computed server-side: assessment minus verified payments</p></div>
    <div class="fms-stat"><h4>Student Number</h4><p class="val" style="font-size:1.1rem;">{{ $receivable->student->student_number ?? '—' }}</p><p class="sub">{{ $receivable->student->program ?? '' }}</p></div>
</div>

<div class="fms-panel">
    <h3>Accounts Receivable Ledger</h3>
    <table class="fms-table">
        <thead><tr><th>Ref No.</th><th>Description</th><th style="text-align:right;">Billed</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Balance</th><th>Due Date</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($receivables ?? [] as $ar)
            <tr><td><strong>{{ $ar->reference_number }}</strong></td><td>{{ $ar->description ?? '—' }}</td><td style="text-align:right;">P{{ number_format((float) $ar->billed_amount, 2) }}</td><td style="text-align:right;">P{{ number_format((float) $ar->paid_amount, 2) }}</td><td style="text-align:right;font-weight:700;">P{{ number_format((float) $ar->balance, 2) }}</td><td>{{ $ar->due_date ? $ar->due_date->format('M d, Y') : '—' }}</td><td><span class="status">{{ ucfirst($ar->status) }}{{ $ar->isOverdue() ? ' (Overdue)' : '' }}</span></td></tr>
        @empty
            <tr><td colspan="7" style="color:#64748b;">No receivables found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="fms-panel">
    <h3>Payment History</h3>
    <table class="fms-table">
        <thead><tr><th>Date</th><th>Transaction</th><th>Method</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr><td>{{ $p->payment_date }}</td><td>{{ $p->transaction_number ?? ('PAY-'.$p->id) }}</td><td>{{ $p->payment_method }}</td><td style="text-align:right;">P{{ number_format($p->amount, 2) }}</td><td><span class="status">{{ ucfirst($p->status) }}</span></td></tr>
        @empty
            <tr><td colspan="5" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="fms-panel">
    <h3>Account Ledger Entries</h3>
    <table class="fms-table">
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody>
        @forelse($ledger as $l)
            <tr><td>{{ $l->created_at?->format('Y-m-d') }}</td><td>{{ $l->entry_type ?? $l->type ?? '—' }}</td><td>{{ $l->description ?? '—' }}</td><td style="text-align:right;">P{{ number_format($l->amount ?? 0, 2) }}</td></tr>
        @empty
            <tr><td colspan="4" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
