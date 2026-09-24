@extends('layouts.app')

@section('title', 'Accountant Dashboard')

@section('content')
<div class="dash-welcome-row">
    <div>
        <h2>Welcome, {{ Auth::user()->name }}</h2>
        <p class="page-subtitle">Monitor financial transactions, receivables, and reconciliation.</p>
    </div>
    <span class="dash-datetime">{{ now()->format('M d, Y h:i A') }}</span>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Collections</h3>
            <p class="card-amount">₱{{ number_format($totalCollections ?? 0, 2) }}</p>
            <p class="summary-desc">Total amount collected</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Outstanding Balance</h3>
            <p class="card-amount">₱{{ number_format($outstandingBalance ?? 0, 2) }}</p>
            <p class="summary-desc">Unpaid balances</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Payments</h3>
            <p class="card-amount">{{ number_format($totalPayments ?? 0) }}</p>
            <p class="summary-desc">Total number of payments</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Unreconciled Transactions</h3>
            <p class="card-amount">{{ number_format($unreconciledCount ?? 0) }}</p>
            <p class="summary-desc">Transactions for review</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <div class="card-head-row">
        <h3>Recent Financial Transactions</h3>
        <a href="{{ route('accountant.payment-records.index') }}" class="view-all-link">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>ID No.</th>
                    <th>Name</th>
                    <th>Payment For</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentTransactions ?? [] as $payment)
                    <tr>
                        <td>{{ $payment->created_at ? $payment->created_at->format('M d, Y h:i A') : ($payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A') }}</td>
                        <td>{{ $payment->student->student_number ?? 'N/A' }}</td>
                        <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                        <td>{{ $payment->description ?? 'N/A' }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td>
                            @if(in_array($payment->status, ['approved', 'posted']))
                                <span class="badge badge-green">{{ ucfirst($payment->status) }}</span>
                            @elseif($payment->status === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($payment->status === 'rejected')
                                <span class="badge badge-red">Rejected</span>
                            @else
                                <span class="badge badge-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No financial transactions found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="two-col-grid">
    <div class="dashboard-card">
        <div class="card-head-row">
            <h3>Outstanding Balance Overview</h3>
            <a href="{{ route('accountant.accounts-receivable.index') }}" class="view-all-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Total Assessment</th>
                        <th>Total Paid</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($outstandingByProgram ?? [] as $row)
                        <tr>
                            <td>{{ $row->program ?? 'N/A' }}</td>
                            <td>₱{{ number_format($row->assessment, 2) }}</td>
                            <td>₱{{ number_format($row->paid, 2) }}</td>
                            <td><strong>₱{{ number_format($row->outstanding, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No outstanding balances</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-head-row">
            <h3>Reconciliation Summary</h3>
            <a href="{{ route('accountant.reconciliation.index') }}" class="view-all-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>System Amount</th>
                        <th>Actual Amount</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliationSummary ?? [] as $row)
                        <tr>
                            <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('M d, Y') : 'N/A' }}</td>
                            <td>₱{{ number_format($row->system_amount, 2) }}</td>
                            <td>₱{{ number_format($row->actual_amount, 2) }}</td>
                            <td>₱{{ number_format($row->difference, 2) }}</td>
                            <td>
                                @if($row->status === 'Reconciled')
                                    <span class="badge badge-green">Reconciled</span>
                                @elseif($row->status === 'Variance')
                                    <span class="badge badge-red">Variance</span>
                                @else
                                    <span class="badge badge-yellow">Unreconciled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No reconciliation records</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
