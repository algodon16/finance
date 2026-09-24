@extends('layouts.app')

@section('title', 'Payment Verification')

@section('content')
<div class="page-header">
    <h2>Payment Verification</h2>
</div>

<div class="filter-tabs">
    <a href="{{ route('cashier.payments.index') }}" class="tab {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('cashier.payments.index', ['status' => 'pending']) }}" class="tab {{ request('status') === 'pending' ? 'active' : '' }}">Pending</a>
    <a href="{{ route('cashier.payments.index', ['status' => 'under_review']) }}" class="tab {{ request('status') === 'under_review' ? 'active' : '' }}">Under Review</a>
    <a href="{{ route('cashier.payments.index', ['status' => 'approved']) }}" class="tab {{ request('status') === 'approved' ? 'active' : '' }}">Approved</a>
    <a href="{{ route('cashier.payments.index', ['status' => 'rejected']) }}" class="tab {{ request('status') === 'rejected' ? 'active' : '' }}">Rejected</a>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Date</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments ?? [] as $payment)
                <tr>
                    <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                    <td>₱{{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->payment_method }}</td>
                    <td>{{ date('M d, Y', strtotime($payment->payment_date)) }}</td>
                    <td>{{ $payment->reference_number ?? 'N/A' }}</td>
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
                    <td>
                        <a href="{{ route('cashier.payments.show', $payment->id) }}" class="btn btn-sm btn-secondary">View Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No payment records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($payments) && method_exists($payments, 'links'))
    <div class="pagination">
        {{ $payments->links() }}
    </div>
@endif
@endsection
