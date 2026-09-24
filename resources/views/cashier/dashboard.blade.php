@extends('layouts.app')

@section('title', 'Cashier Dashboard')

@section('content')
@php
    $firstName = explode(' ', trim(Auth::user()->name ?? 'Cashier'))[0];
    $methodLabels = [
        'bank_transfer' => 'Bank Transfer',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'other' => 'Over-the-Counter',
        'cash' => 'Cash',
    ];
@endphp

<div class="cashier-dashboard">
    <div class="dash-head">
        <div>
            <h2>Cashier Dashboard</h2>
            <p class="dash-welcome">Welcome back, {{ $firstName }}! Here's your collection overview for today.</p>
        </div>
        <div class="term-card">
            <strong>Today</strong>
            <span>{{ now()->format('F j, Y') }}</span>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card sum-green">
            <h3>Total Collections</h3>
            <p class="summary-amount">₱{{ number_format($totalCollections ?? 0, 2) }}</p>
            <p class="summary-sub">Total verified collections</p>
        </div>

        <div class="summary-card sum-blue">
            <h3>Total Transactions</h3>
            <p class="summary-amount">{{ $totalTransactions ?? 0 }}</p>
            <p class="summary-sub">All payment submissions</p>
        </div>

        <div class="summary-card sum-orange">
            <h3>Pending Verification</h3>
            <p class="summary-amount">{{ $pendingCount ?? 0 }}</p>
            <p class="summary-sub">For verification</p>
        </div>

        <div class="summary-card sum-green">
            <h3>Verified Payments</h3>
            <p class="summary-amount">{{ $verifiedCount ?? 0 }}</p>
            <p class="summary-sub">Approved payments</p>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-head">
            <h3>Recent Transactions</h3>
            <a href="{{ route('cashier.reports.summary') }}" class="panel-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference No.</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentTransactions ?? [] as $payment)
                        <tr>
                            <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                            <td>₱{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $methodLabels[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                            <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                            <td>
                                @if($payment->status === 'pending')
                                    <span class="badge badge-yellow">Pending</span>
                                @elseif($payment->status === 'under_review')
                                    <span class="badge badge-blue">Under Review</span>
                                @elseif($payment->status === 'approved')
                                    <span class="badge badge-green">Verified</span>
                                @else
                                    <span class="badge badge-red">Rejected</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('cashier.payments.show', $payment->id) }}" class="btn btn-sm btn-secondary">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No transactions found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@push('scripts')
<script>
    (function () {
        function tick() {
            var el = document.getElementById('topBarClock');
            if (!el) return;
            var now = new Date();
            var time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            var date = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            el.textContent = date + ' | ' + time;
        }
        tick();
        setInterval(tick, 30000);
    })();
</script>
@endpush
@endsection
