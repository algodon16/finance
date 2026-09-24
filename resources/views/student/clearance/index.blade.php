@extends('layouts.app')

@section('title', 'Financial Clearance')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumbs">
            <a href="{{ route('student.dashboard') }}">Dashboard</a>
            <span class="separator">></span>
            <span class="current">Clearance</span>
        </div>
        <h2>Financial Clearance</h2>
        <p class="page-subtitle">View your charges and settle all outstanding balances to obtain your financial clearance.</p>
    </div>
    <div class="page-header-right">
        @if(($clearanceStatus ?? '') === 'cleared')
            <span class="badge badge-green badge-large">CLEARED</span>
        @elseif(($clearanceStatus ?? '') === 'pending_review')
            <span class="badge badge-yellow badge-large">PENDING REVIEW</span>
        @else
            <span class="badge badge-red badge-large">NOT CLEARED</span>
        @endif
        <span class="dash-datetime">{{ now()->format('M d, Y h:i A') }}</span>
    </div>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Charges</h3>
            <p class="card-amount">₱{{ number_format($totalCharges ?? 0, 2) }}</p>
            <p class="summary-desc">Total assessed fees</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Payments</h3>
            <p class="card-amount">₱{{ number_format($totalPayments ?? 0, 2) }}</p>
            <p class="summary-desc">Total amount paid</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Outstanding Balance</h3>
            <p class="card-amount">₱{{ number_format($outstandingBalance ?? 0, 2) }}</p>
            <p class="summary-desc">Remaining balance</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Fee Assessment</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charges ?? [] as $index => $charge)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $charge->description }}</td>
                        <td>₱{{ number_format($charge->amount, 2) }}</td>
                        <td>₱{{ number_format($charge->paid_amount ?? 0, 2) }}</td>
                        <td>₱{{ number_format($charge->balance_amount ?? $charge->amount, 2) }}</td>
                        <td>
                            @if(($charge->balance_amount ?? $charge->amount) <= 0.009)
                                <span class="badge badge-green">Paid</span>
                            @elseif(($charge->paid_amount ?? 0) > 0)
                                <span class="badge badge-yellow">Partial</span>
                            @else
                                <span class="badge badge-red">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No fee assessment found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="form-actions form-actions-center">
        <a href="{{ route('student.payments.index') }}" class="btn btn-primary">Go to Payment</a>
    </div>
</div>
@endsection
