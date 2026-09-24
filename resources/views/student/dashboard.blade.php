@extends('layouts.app')

@section('title', 'Financial Dashboard')

@section('content')
@php
    $firstName = $student->first_name ?? explode(' ', trim(Auth::user()->name ?? 'Student'))[0];
    $termLabel = isset($semester) && $semester ? $semester->name : '1st Semester';
    $yearLabel = isset($academicYear) && $academicYear ? $academicYear->name : '2025-2026';
    $isCleared = ($clearanceStatus ?? 'not_cleared') === 'cleared';
@endphp

<div class="student-dashboard">
    <div class="dash-head">
        <div>
            <h2>Financial Dashboard</h2>
            <p class="dash-welcome">Welcome back, {{ $firstName }}! Here's your financial overview for {{ $termLabel }} {{ $yearLabel }}.</p>
        </div>
        <div class="term-card">
            <strong>Academic Year {{ $yearLabel }}</strong>
            <span>{{ $termLabel }}</span>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card sum-blue">
            <h3>Total Balance</h3>
            <p class="summary-amount">₱{{ number_format($totalBalance ?? $totalCharges ?? 0, 2) }}</p>
            <p class="summary-sub">Total assessed fees</p>
        </div>

        <div class="summary-card sum-green">
            <h3>Total Paid</h3>
            <p class="summary-amount">₱{{ number_format($totalPaid ?? 0, 2) }}</p>
            <p class="summary-sub">Payments received</p>
        </div>

        <div class="summary-card sum-red">
            <h3>Remaining Balance</h3>
            <p class="summary-amount">₱{{ number_format($outstandingBalance ?? 0, 2) }}</p>
            <p class="summary-sub">Amount still due</p>
        </div>

        <div class="summary-card sum-yellow">
            <h3>Pending Payments</h3>
            <p class="summary-amount">{{ $pendingPayments ?? 0 }}</p>
            <p class="summary-sub">For verification</p>
        </div>
    </div>

    <div class="panel-card">
        <h3>Clearance Status</h3>
        <div class="clearance-row">
            @if($isCleared)
                <span class="badge badge-green">CLEARED</span>
            @else
                <span class="badge badge-red">NOT CLEARED</span>
            @endif
        </div>
        <p class="clearance-message">
            @if($isCleared)
                Your financial account is settled.
            @else
                You still have outstanding balances.
            @endif
        </p>
    </div>

    <div class="tables-grid">
        <div class="panel-card">
            <h3>Recent Transactions</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions ?? [] as $transaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction->created_at)->format('M d, Y') }}</td>
                                <td>{{ $transaction->description }}</td>
                                <td>₱{{ number_format($transaction->amount, 2) }}</td>
                                <td>
                                    @php $tStatus = strtolower($transaction->status ?? ''); @endphp
                                    @if($tStatus === 'paid')
                                        <span class="badge badge-green">{{ ucfirst($transaction->status) }}</span>
                                    @elseif($tStatus === 'active')
                                        <span class="badge badge-blue">{{ ucfirst($transaction->status) }}</span>
                                    @elseif($tStatus === 'pending')
                                        <span class="badge badge-yellow">{{ ucfirst($transaction->status) }}</span>
                                    @elseif(in_array($tStatus, ['waived', 'cancelled']))
                                        <span class="badge badge-gray">{{ ucfirst($transaction->status) }}</span>
                                    @else
                                        <span class="badge badge-red">{{ ucfirst($transaction->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No recent transactions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card">
            <h3>Recent Payment Submissions</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($recentPaymentSubmissions ?? $recentPayments ?? []) as $payment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y') }}</td>
                                <td>₱{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td>
                                    @if(in_array($payment->status, ['pending', 'under_review']))
                                        <span class="badge badge-yellow">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                                    @elseif($payment->status === 'approved')
                                        <span class="badge badge-green">Approved</span>
                                    @elseif($payment->status === 'posted')
                                        <span class="badge badge-darkgreen">Posted</span>
                                    @elseif($payment->status === 'rejected')
                                        <span class="badge badge-red">Rejected</span>
                                    @elseif($payment->status === 'cancelled')
                                        <span class="badge badge-gray">Cancelled</span>
                                    @else
                                        <span class="badge badge-gray">{{ ucfirst($payment->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No payment submissions yet</td>
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
