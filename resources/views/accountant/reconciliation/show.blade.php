@extends('layouts.app')

@section('title', 'Reconciliation Details')

@section('content')
<div class="page-header">
    <div>
        <h2>Reconciliation Details</h2>
        <p class="page-subtitle">Difference = Actual Amount - System Amount. Historical records are read-only.</p>
    </div>
    <a href="{{ route('accountant.reconciliation.index') }}" class="btn btn-secondary">Back to Reconciliation</a>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>System Amount</h3>
            <p class="card-amount">₱{{ number_format($systemAmount, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Actual Amount</h3>
            <p class="card-amount">₱{{ number_format($actualAmount, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Difference</h3>
            <p class="card-amount">₱{{ number_format($difference, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Status</h3>
            <p class="card-amount status-text">
                @if($status === 'Reconciled')
                    <span class="badge badge-green">Reconciled</span>
                @elseif($status === 'Variance')
                    <span class="badge badge-red">Variance</span>
                @else
                    <span class="badge badge-yellow">Unreconciled</span>
                @endif
            </p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Recorded Payment (System)</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Date</span>
            <p class="detail-value">{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Student</span>
            <p class="detail-value">{{ $payment->student->full_name ?? 'N/A' }} ({{ $payment->student->student_number ?? 'N/A' }})</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment For</span>
            <p class="detail-value">{{ $payment->description ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Reference</span>
            <p class="detail-value">{{ $payment->reference_number ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Method</span>
            <p class="detail-value">{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment Status</span>
            <p class="detail-value">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Actual Financial Records (Ledger)</h3>
    @if($hasLedger)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->accountLedgerEntries as $entry)
                        <tr>
                            <td>{{ $entry->transaction_date ? $entry->transaction_date->format('M d, Y') : 'N/A' }}</td>
                            <td>{{ $entry->reference_number }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>₱{{ number_format($entry->credit, 2) }}</td>
                            <td>₱{{ number_format($entry->balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted-text">No ledger entry found for this payment. It is treated as unreconciled until the collection record is posted.</p>
    @endif
</div>
@endsection
