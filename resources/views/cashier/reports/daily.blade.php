@extends('layouts.app')

@section('title', 'Daily Collections Report')

@section('content')
<div class="page-header">
    <h2>Daily Collections Report</h2>
    <a href="{{ route('cashier.reports.index') }}" class="btn btn-secondary">Back to Reports</a>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('cashier.reports.daily') }}" class="filter-form">
        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" name="date" id="date" class="form-control" value="{{ request('date', date('Y-m-d')) }}">
        </div>
        <button type="submit" class="btn btn-secondary">Generate Report</button>
    </form>
    <div class="form-group">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Report</button>
    </div>
</div>

<div class="report-content">
    <div class="summary-cards">
        <div class="dashboard-card card-green">
            <div class="card-content">
                <h3>Total Collections</h3>
                <p class="card-amount">₱{{ number_format($dailyTotal ?? 0, 2) }}</p>
            </div>
        </div>
        <div class="dashboard-card card-blue">
            <div class="card-content">
                <h3>Transaction Count</h3>
                <p class="card-amount">{{ $dailyCount ?? 0 }}</p>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dailyCollections ?? [] as $collection)
                    <tr>
                        <td>{{ $collection->student->full_name ?? 'N/A' }}</td>
                        <td>₱{{ number_format($collection->amount, 2) }}</td>
                        <td>{{ $collection->payment_method ?? 'N/A' }}</td>
                        <td>{{ $collection->reference_number ?? 'N/A' }}</td>
                        <td>{{ $collection->description ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No collections found for this date</td>
                    </tr>
                @endforelse
            </tbody>
            @if(isset($dailyCollections) && $dailyCollections->count() > 0)
                <tfoot>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td><strong>₱{{ number_format($dailyTotal ?? 0, 2) }}</strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@push('scripts')
<style>
    @media print {
        .page-header, .filter-bar, .sidebar, .top-bar, .btn { display: none !important; }
        .main-content { margin: 0 !important; padding: 10px !important; }
    }
</style>
@endpush
@endsection
