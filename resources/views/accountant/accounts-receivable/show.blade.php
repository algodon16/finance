@extends('layouts.app')

@section('title', 'Receivable Account Details')

@section('content')
<div class="page-header">
    <div>
        <h2>Receivable Account Details</h2>
        <p class="page-subtitle">{{ $student->full_name ?? 'Student' }} ({{ $student->student_number ?? '' }})</p>
    </div>
    <a href="{{ route('accountant.accounts-receivable.index') }}" class="btn btn-secondary">Back to Receivables</a>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Assessment</h3>
            <p class="card-amount">₱{{ number_format($account->total_charges ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Paid</h3>
            <p class="card-amount">₱{{ number_format($account->total_paid ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Outstanding Balance</h3>
            <p class="card-amount">₱{{ number_format($account->outstanding_balance ?? 0, 2) }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Assessments / Charges</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charges as $charge)
                    <tr>
                        <td>{{ $charge->description }}</td>
                        <td>{{ $charge->financialCategory->name ?? 'N/A' }}</td>
                        <td>₱{{ number_format($charge->amount, 2) }}</td>
                        <td>{{ $charge->due_date ? $charge->due_date->format('M d, Y') : 'N/A' }}</td>
                        <td><span class="badge badge-gray">{{ ucfirst($charge->status) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No charges found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <h3>Recorded Payments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Payment For</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $payment->description ?? 'N/A' }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td><span class="badge badge-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No recorded payments found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
