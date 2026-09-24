@extends('layouts.app')

@section('title', 'Payment Recorded')

@section('content')
@php
    $yl = $payment->student->year_level;
    $suffix = 'th';
    if (is_numeric($yl)) {
        $yl = (int) $yl;
        $v = $yl % 100;
        $suffix = ($v >= 11 && $v <= 13) ? 'th' : (['th','st','nd','rd'][$yl % 10] ?? 'th');
        if ($yl % 10 > 3) $suffix = 'th';
        $yearLabel = $yl . $suffix . ' Year';
    } else {
        $yearLabel = $yl ?? 'N/A';
    }
@endphp

<div class="page-header no-print">
    <div>
        <h2>Payment Recorded Successfully</h2>
    </div>
    <a href="{{ route('cashier.payment.index') }}" class="btn btn-secondary">New Payment</a>
</div>

<div class="detail-card success-card no-print">
    <div class="confirm-row"><span>Student:</span><strong>{{ $payment->student->full_name ?? 'N/A' }}</strong></div>
    <div class="confirm-row"><span>Student ID:</span><strong>{{ $payment->student->student_number ?? 'N/A' }}</strong></div>
    <div class="confirm-row"><span>Payment For:</span><strong>{{ $payment->description ?? 'N/A' }}</strong></div>
    <div class="confirm-row"><span>Amount Paid:</span><strong>₱{{ number_format($payment->amount, 2) }}</strong></div>
    <div class="confirm-row"><span>Previous Balance:</span><strong>₱{{ number_format($previous, 2) }}</strong></div>
    <div class="confirm-row"><span>Remaining Balance:</span><strong>₱{{ number_format($remaining, 2) }}</strong></div>
    <div class="confirm-row"><span>Official Receipt No.:</span><strong>{{ $payment->reference_number }}</strong></div>
    <div class="success-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        <a href="{{ route('cashier.payment.index') }}" class="btn btn-secondary">New Payment</a>
    </div>
</div>

<div class="receipt-card" id="officialReceipt">
    <div class="receipt-head">
        <h3>BESTLINK COLLEGE OF THE PHILIPPINES</h3>
        <p class="receipt-title">OFFICIAL RECEIPT</p>
    </div>
    <div class="receipt-body">
        <div class="receipt-row"><span>Receipt No.</span><strong>{{ $payment->reference_number }}</strong></div>
        <div class="receipt-row"><span>Date &amp; Time</span><strong>{{ $payment->reviewed_at ? $payment->reviewed_at->format('F j, Y g:i A') : $payment->created_at->format('F j, Y g:i A') }}</strong></div>
        <div class="receipt-row"><span>Student ID</span><strong>{{ $payment->student->student_number ?? 'N/A' }}</strong></div>
        <div class="receipt-row"><span>Student Name</span><strong>{{ $payment->student->full_name ?? 'N/A' }}</strong></div>
        <div class="receipt-row"><span>Program</span><strong>{{ $payment->student->program ?? 'N/A' }}</strong></div>
        <div class="receipt-row"><span>Year Level</span><strong>{{ $yearLabel }}</strong></div>
        <div class="receipt-row"><span>Payment For</span><strong>{{ $payment->description ?? 'N/A' }}</strong></div>
        <div class="receipt-row"><span>Amount Paid</span><strong>₱{{ number_format($payment->amount, 2) }}</strong></div>
        <div class="receipt-row"><span>Payment Method</span><strong>Cash</strong></div>
        <div class="receipt-row"><span>Previous Balance</span><strong>₱{{ number_format($previous, 2) }}</strong></div>
        <div class="receipt-row"><span>Remaining Balance</span><strong>₱{{ number_format($remaining, 2) }}</strong></div>
        <div class="receipt-row"><span>Received By</span><strong>{{ $payment->reviewer->name ?? 'Cashier' }}<br><small>Cashier</small></strong></div>
    </div>
    <div class="receipt-foot no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
    </div>
</div>

@push('scripts')
<style>
    .success-card { max-width: 560px; margin: 0 auto 24px; padding: 24px; }
    .confirm-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
    .confirm-row span { color: #64748b; }
    .success-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
    .receipt-card { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 32px; }
    .receipt-head { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 16px; margin-bottom: 16px; }
    .receipt-head h3 { margin: 0 0 4px; color: #1e3a5f; }
    .receipt-title { margin: 0; letter-spacing: 3px; font-weight: 700; }
    .receipt-row { display: flex; justify-content: space-between; gap: 16px; padding: 7px 0; border-bottom: 1px dotted #e5e7eb; }
    .receipt-row span { color: #64748b; }
    .receipt-row strong { text-align: right; }
    .receipt-foot { display: flex; justify-content: flex-end; margin-top: 20px; }
    @media print {
        .sidebar, .top-bar, .page-header, .no-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .content-area { padding: 0 !important; }
        .receipt-card { border: none; max-width: 100%; margin: 0; padding: 0; }
        .receipt-foot { display: none !important; }
    }
</style>
@endpush
@endsection
