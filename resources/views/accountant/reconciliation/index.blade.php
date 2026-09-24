@extends('layouts.app')

@section('title', 'Reconciliation')

@section('content')
<div class="page-header">
    <div>
        <h2>Reconciliation</h2>
        <p class="page-subtitle">Compare recorded payments and identify financial variances.</p>
    </div>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Records</h3>
            <p class="card-amount">{{ number_format($totalRecords ?? 0) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Reconciled</h3>
            <p class="card-amount">{{ number_format($reconciled ?? 0) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Unreconciled</h3>
            <p class="card-amount">{{ number_format($unreconciled ?? 0) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Variance</h3>
            <p class="card-amount">₱{{ number_format($totalVariance ?? 0, 2) }}</p>
        </div>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('accountant.reconciliation.index') }}" class="filter-form">
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
            <label for="status_filter">Status</label>
            <select name="status_filter" id="status_filter" class="form-control">
                <option value="">All</option>
                <option value="Reconciled" {{ request('status_filter') === 'Reconciled' ? 'selected' : '' }}>Reconciled</option>
                <option value="Variance" {{ request('status_filter') === 'Variance' ? 'selected' : '' }}>Variance</option>
                <option value="Unreconciled" {{ request('status_filter') === 'Unreconciled' ? 'selected' : '' }}>Unreconciled</option>
            </select>
        </div>
        <div class="form-actions-inline">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('accountant.reconciliation.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h3>Reconciliation Details</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Student</th>
                    <th>System Amount</th>
                    <th>Actual Amount</th>
                    <th>Difference</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $row)
                    <tr>
                        <td>{{ $row->payment->payment_date ? $row->payment->payment_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $row->payment->reference_number ?? 'N/A' }}</td>
                        <td>{{ $row->payment->student->full_name ?? 'N/A' }}</td>
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
                        <td><a href="{{ route('accountant.reconciliation.show', $row->payment->id) }}" class="btn btn-sm btn-secondary">View Details</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No reconciliation records found</td>
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
