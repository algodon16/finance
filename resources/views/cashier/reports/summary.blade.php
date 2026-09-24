@extends('layouts.app')

@section('title', 'Payment Verification Summary')

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
    <div>
        <h2>Payment Verification Summary</h2>
        <p class="page-subtitle">Review and monitor student payment verifications.</p>
    </div>
    <a href="{{ route('cashier.reports.index') }}" class="btn btn-secondary">Back to Reports</a>
</div>

<div class="pvs-filter-card">
    <form method="GET" action="{{ route('cashier.reports.summary') }}" class="pvs-filter-form">
        @if(request('search'))
            <input type="hidden" name="search" value="{{ request('search') }}">
        @endif
        <div class="form-group">
            <label for="date_from">Date From</label>
            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from', $dateFrom ?? '') }}">
        </div>
        <div class="form-group">
            <label for="date_to">Date To</label>
            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to', $dateTo ?? '') }}">
        </div>
        <div class="pvs-filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('cashier.reports.summary') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="pvs-grid pvs-grid-4">
    <div class="pvs-card pvs-orange">
        <p class="pvs-label">Pending</p>
        <p class="pvs-value">{{ $pendingCount ?? 0 }}</p>
    </div>
    <div class="pvs-card pvs-blue">
        <p class="pvs-label">Under Review</p>
        <p class="pvs-value">{{ $underReviewCount ?? 0 }}</p>
    </div>
    <div class="pvs-card pvs-green">
        <p class="pvs-label">Approved</p>
        <p class="pvs-value">{{ $approvedCount ?? 0 }}</p>
    </div>
    <div class="pvs-card pvs-red">
        <p class="pvs-label">Rejected</p>
        <p class="pvs-value">{{ $rejectedCount ?? 0 }}</p>
    </div>
</div>

<div class="pvs-grid pvs-grid-2">
    <div class="pvs-card pvs-green">
        <p class="pvs-label">Total Approved Amount</p>
        <p class="pvs-value">₱{{ number_format($totalApprovedAmount ?? 0, 2) }}</p>
    </div>
    <div class="pvs-card pvs-red">
        <p class="pvs-label">Total Rejected Amount</p>
        <p class="pvs-value">₱{{ number_format($totalRejectedAmount ?? 0, 2) }}</p>
    </div>
</div>

<div class="pvs-table-card">
    <div class="pvs-table-head">
        <h3>Verification Details</h3>
        <form method="GET" action="{{ route('cashier.reports.summary') }}" class="pvs-search-form">
            <input type="hidden" name="date_from" value="{{ request('date_from', $dateFrom ?? '') }}">
            <input type="hidden" name="date_to" value="{{ request('date_to', $dateTo ?? '') }}">
            <input type="search" name="search" class="form-control" placeholder="Search student name, reference..."
                value="{{ request('search') }}" autocomplete="off">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table pvs-table">
            <thead>
                <tr>
                    <th class="pvs-col-num">#</th>
                    <th>Student Name</th>
                    <th class="pvs-num">Amount</th>
                    <th>Payment For</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($verifications ?? [] as $payment)
                    <tr>
                        <td class="pvs-col-num">{{ ($verifications->firstItem() ?? 0) + $loop->index }}</td>
                        <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                        <td class="pvs-num">₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->description ?? 'N/A' }}</td>
                        <td>{{ $methodLabels[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td>{{ date('M d, Y', strtotime($payment->payment_date)) }}</td>
                        <td>
                            @if($payment->status === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($payment->status === 'under_review')
                                <span class="badge badge-blue">Under Review</span>
                            @elseif($payment->status === 'approved')
                                <span class="badge badge-green">Approved</span>
                            @else
                                <span class="badge badge-red">Rejected</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No verification records found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($verifications) && method_exists($verifications, 'total'))
        <div class="pvs-pagination">
            <div class="pagination">
                {{ $verifications->links() }}
            </div>
        </div>
    @endif
</div>

@push('scripts')
<style>
    .page-subtitle { margin: 4px 0 0; color: #666; font-size: 0.95rem; }
    .pvs-filter-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); padding: 16px 20px; margin-bottom: 16px; }
    .pvs-filter-form { display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
    .pvs-filter-form .form-group { margin-bottom: 0; min-width: 180px; }
    .pvs-filter-actions { display: flex; gap: 10px; }
    .pvs-grid { display: grid; gap: 16px; margin-bottom: 16px; }
    .pvs-grid-4 { grid-template-columns: repeat(4, 1fr); }
    .pvs-grid-2 { grid-template-columns: repeat(2, 1fr); }
    .pvs-card { background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); padding: 16px 20px; min-height: 96px; display: flex; flex-direction: column; justify-content: center; }
    .pvs-card.pvs-orange { border-left-color: #f59e0b; }
    .pvs-card.pvs-blue { border-left-color: #3b82f6; }
    .pvs-card.pvs-green { border-left-color: #22c55e; }
    .pvs-card.pvs-red { border-left-color: #ef4444; }
    .pvs-label { margin: 0 0 4px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
    .pvs-value { margin: 0; font-size: 1.6rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .pvs-table-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); padding: 20px; }
    .pvs-table-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
    .pvs-table-head h3 { margin: 0; font-size: 1.05rem; }
    .pvs-search-form { display: flex; gap: 8px; }
    .pvs-search-form .form-control { min-width: 240px; }
    .pvs-table thead th { background: #f8fafc; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; font-weight: 700; border-bottom: 1px solid #e5e7eb; }
    .pvs-table tbody td { border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; vertical-align: middle; }
    .pvs-table tbody tr:last-child td { border-bottom: none; }
    .pvs-num { text-align: right; white-space: nowrap; }
    .pvs-col-num { width: 48px; color: #64748b; }
    .pvs-pagination { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9; }
    .pvs-showing { margin: 0; font-size: 0.85rem; color: #64748b; }
    .pvs-pagination .pagination { margin: 0; }
    @media (max-width: 1024px) {
        .pvs-grid-4 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .pvs-grid-4, .pvs-grid-2 { grid-template-columns: 1fr; }
        .pvs-table-head { align-items: stretch; flex-direction: column; }
        .pvs-search-form .form-control { flex: 1; min-width: 0; }
        .pvs-pagination { flex-direction: column; align-items: flex-start; }
    }
</style>
@endpush
@endsection
