@extends('layouts.app')

@section('title', 'Payment Details')

@section('content')
@php
    $methodLabels = [
        'bank_transfer' => 'Bank Transfer',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'other' => 'Over-the-Counter',
        'cash' => 'Cash',
    ];
@endphp

<div class="page-header">
    <h2>Payment Details</h2>
    <div style="display:flex; gap:8px;">
        @if(in_array($payment->status, ['pending', 'rejected']))
            <a href="{{ route('student.payments.edit', $payment->id) }}" class="btn btn-secondary">Edit</a>
        @endif
        <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Back to Payments</a>
    </div>
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Payment #{{ $payment->id }}</h3>
        @if($payment->status === 'pending')
            <span class="badge badge-yellow">Pending</span>
        @elseif($payment->status === 'under_review')
            <span class="badge badge-blue">Under Review</span>
        @elseif($payment->status === 'approved')
            <span class="badge badge-green">Approved</span>
        @elseif($payment->status === 'rejected')
            <span class="badge badge-red">Rejected</span>
        @elseif($payment->status === 'posted')
            <span class="badge badge-darkgreen">Posted</span>
        @elseif($payment->status === 'cancelled')
            <span class="badge badge-gray">Cancelled</span>
        @else
            <span class="badge badge-gray">{{ ucfirst($payment->status) }}</span>
        @endif
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Amount</span>
            <p class="detail-value">₱{{ number_format($payment->amount, 2) }}</p>
        </div>

        <div class="detail-item">
            <span class="detail-label">Payment Method</span>
            <p class="detail-value">{{ $methodLabels[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</p>
        </div>

        <div class="detail-item">
            <span class="detail-label">Reference Number</span>
            <p class="detail-value">{{ $payment->reference_number ?? 'N/A' }}</p>
        </div>

        @include('payments.partials.verification', ['payment' => $payment])

        <div class="detail-item">
            <span class="detail-label">Payment Date</span>
            <p class="detail-value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('F d, Y') }}</p>
        </div>

        <div class="detail-item">
            <span class="detail-label">Submitted On</span>
            <p class="detail-value">{{ \Carbon\Carbon::parse($payment->created_at)->format('F d, Y h:i A') }}</p>
        </div>

        <div class="detail-item">
            <span class="detail-label">Description</span>
            <p class="detail-value">{{ $payment->description ?? 'No description' }}</p>
        </div>
    </div>

    @if($payment->status === 'rejected' && $payment->rejection_reason)
        <div class="rejection-reason">
            <h4>Rejection Reason</h4>
            <p>{{ $payment->rejection_reason }}</p>
        </div>
    @endif

    @if(!in_array($payment->status, ['pending', 'rejected']))
        <div class="alert alert-info">This payment has already been verified and cannot be edited. Please contact the Cashier or Accounting Office.</div>
    @endif

    <div class="proof-section">
        <h4>Proof of Payment</h4>
        @if(isset($payment->paymentProofs) && $payment->paymentProofs->count() > 0)
            @foreach($payment->paymentProofs as $proof)
                <div class="current-proof">
                    <span>{{ $proof->file_name }}</span>
                    <a href="{{ route('student.payments.proof', [$payment->id, $proof->id]) }}" target="_blank" class="btn btn-sm btn-secondary">View Proof</a>
                </div>
            @endforeach
        @else
            <p class="text-muted">No proof of payment uploaded.</p>
        @endif
    </div>
</div>

<style>
    .current-proof { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f7fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 12px; margin-bottom: 8px; font-size: .875rem; }
    .text-muted { color: var(--text-muted); }
</style>
@endsection
