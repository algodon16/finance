@extends('layouts.app')

@section('title', 'Payment Records')

@section('content')
<div class="page-header">
    <div>
        <h2>Payment Records</h2>
        <p class="page-subtitle">Review posted and recorded student payment transactions.</p>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('accountant.payment-records.index') }}" class="filter-form">
        <div class="form-group">
            <label for="search">Search</label>
            <input type="text" name="search" id="search" placeholder="Search student, ID, reference..." value="{{ request('search') }}" class="form-control">
        </div>
        <div class="form-group">
            <label for="date_from">Date From</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="form-control">
        </div>
        <div class="form-group">
            <label for="date_to">Date To</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="form-control">
        </div>
        <div class="form-group">
            <label for="payment_method">Payment Method</label>
            <select name="payment_method" id="payment_method" class="form-control">
                <option value="">All Methods</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method }}" {{ request('payment_method') === $method ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="status">Payment Status</label>
            <select name="status" id="status" class="form-control">
                <option value="">All Statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="payment_for">Payment For</label>
            <select name="payment_for" id="payment_for" class="form-control">
                <option value="">All Types</option>
                @foreach($paymentForOptions as $option)
                    <option value="{{ $option }}" {{ request('payment_for') === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions-inline">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('accountant.payment-records.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Payment For</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $index => $payment)
                    <tr>
                        <td>{{ $payments->firstItem() + $index }}</td>
                        <td>{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $payment->student->student_number ?? 'N/A' }}</td>
                        <td><a href="{{ route('accountant.payment-records.show', $payment->id) }}">{{ $payment->student->full_name ?? 'N/A' }}</a></td>
                        <td>{{ $payment->description ?? 'N/A' }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td><span class="badge badge-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No payment records found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        {{ $payments->links() }}
    </div>
</div>
@endsection
