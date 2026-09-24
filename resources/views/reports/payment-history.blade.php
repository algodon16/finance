@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
<div class="page-header">
    <h2>Payment History</h2>
    <h3>{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</h3>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Reviewed At</th>
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
                        <td>{{ $payment->reviewed_at ? date('M d, Y', strtotime($payment->reviewed_at)) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No payment history found</td>
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
