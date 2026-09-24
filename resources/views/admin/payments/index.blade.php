@extends('layouts.app')

@section('title', 'Payments')

@section('content')
<div class="page-header">
    <h2>Payments</h2>
</div>

<div class="summary-cards">
    <div class="dashboard-card card-green">
        <div class="card-content">
            <h3>Total Collected</h3>
            <p class="card-amount">₱{{ number_format($totalCollected ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-yellow">
        <div class="card-content">
            <h3>Pending</h3>
            <p class="card-amount">{{ $pendingCount ?? 0 }}</p>
        </div>
    </div>
    <div class="dashboard-card card-blue">
        <div class="card-content">
            <h3>Approved</h3>
            <p class="card-amount">{{ $approvedCount ?? 0 }}</p>
        </div>
    </div>
    <div class="dashboard-card card-red">
        <div class="card-content">
            <h3>Rejected</h3>
            <p class="card-amount">{{ $rejectedCount ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->payment_method }}</td>
                        <td>{{ $payment->payment_date ? date('M d, Y', strtotime($payment->payment_date)) : 'N/A' }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td>
                            <span class="badge badge-{{ $payment->status === 'approved' ? 'green' : ($payment->status === 'rejected' ? 'red' : ($payment->status === 'under_review' ? 'blue' : 'yellow')) }}">
                                {{ ucfirst(str_replace('_', ' ', $payment->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No payments found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">
        {{ $payments->links() }}
    </div>
</div>
@endsection
