@extends('layouts.app')

@section('title', 'Payment Record Details')

@section('content')
<div class="page-header">
    <div>
        <h2>Payment Record Details</h2>
        <p class="page-subtitle">Read-only financial review. Payment processing belongs to the Cashier workflow.</p>
    </div>
    <a href="{{ route('accountant.payment-records.index') }}" class="btn btn-secondary">Back to Payment Records</a>
</div>

<div class="dashboard-card">
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Date</span>
            <p class="detail-value">{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Student ID</span>
            <p class="detail-value">{{ $payment->student->student_number ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Student Name</span>
            <p class="detail-value">{{ $payment->student->full_name ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment For</span>
            <p class="detail-value">{{ $payment->description ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Amount</span>
            <p class="detail-value">₱{{ number_format($payment->amount, 2) }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Method</span>
            <p class="detail-value">{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Reference</span>
            <p class="detail-value">{{ $payment->reference_number ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Status</span>
            <p class="detail-value"><span class="badge badge-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span></p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Reviewed By</span>
            <p class="detail-value">{{ $payment->reviewer->name ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Reviewed At</span>
            <p class="detail-value">{{ $payment->reviewed_at ? $payment->reviewed_at->format('M d, Y h:i A') : 'N/A' }}</p>
        </div>
    </div>
</div>

@if($payment->accountLedgerEntries && $payment->accountLedgerEntries->count())
<div class="dashboard-card">
    <h3>Linked Ledger Entries</h3>
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
</div>
@endif
@endsection
