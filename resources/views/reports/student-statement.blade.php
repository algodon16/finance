@extends('layouts.app')

@section('title', 'Student Statement of Account')

@section('content')
<div class="page-header">
    <h2>Statement of Account</h2>
    <h3>{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }} ({{ $student->student_number ?? '' }})</h3>
</div>

<div class="summary-cards">
    <div class="dashboard-card card-red">
        <div class="card-content">
            <h3>Total Charges</h3>
            <p class="card-amount">₱{{ number_format($totalCharges ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-green">
        <div class="card-content">
            <h3>Total Payments</h3>
            <p class="card-amount">₱{{ number_format($totalPayments ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-blue">
        <div class="card-content">
            <h3>Outstanding Balance</h3>
            <p class="card-amount">₱{{ number_format($outstandingBalance ?? 0, 2) }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Charges</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charges as $charge)
                    <tr>
                        <td>{{ date('M d, Y', strtotime($charge->created_at)) }}</td>
                        <td>{{ $charge->financialCategory->name ?? 'N/A' }}</td>
                        <td>{{ $charge->description ?? '-' }}</td>
                        <td>₱{{ number_format($charge->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No charges found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <h3>Payments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Reviewer</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ date('M d, Y', strtotime($payment->created_at)) }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->payment_method ?? 'N/A' }}</td>
                        <td>
                            <span class="badge badge-{{ $payment->status === 'approved' ? 'green' : ($payment->status === 'pending' ? 'yellow' : 'red') }}">
                                {{ ucfirst($payment->status) }}
                            </span>
                        </td>
                        <td>{{ $payment->reviewer->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No payments found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <h3>Ledger</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledger as $entry)
                    <tr>
                        <td>{{ date('M d, Y', strtotime($entry->transaction_date)) }}</td>
                        <td>{{ $entry->reference_number }}</td>
                        <td>{{ $entry->description }}</td>
                        <td>{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}</td>
                        <td>{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}</td>
                        <td>₱{{ number_format($entry->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No ledger entries found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
