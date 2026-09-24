@extends('layouts.app')

@section('title', 'Collection Summary')

@section('content')
<div class="page-header">
    <h2>Collection Summary</h2>
</div>

<div class="summary-cards">
    <div class="dashboard-card card-green">
        <div class="card-content">
            <h3>Total Collections</h3>
            <p class="card-amount">₱{{ number_format($totalCollections ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-blue">
        <div class="card-content">
            <h3>Approved</h3>
            <p class="card-amount">{{ $totalApproved ?? 0 }}</p>
        </div>
    </div>
    <div class="dashboard-card card-red">
        <div class="card-content">
            <h3>Rejected</h3>
            <p class="card-amount">{{ $totalRejected ?? 0 }}</p>
        </div>
    </div>
    <div class="dashboard-card card-yellow">
        <div class="card-content">
            <h3>Pending</h3>
            <p class="card-amount">{{ $totalPending ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Monthly Collections</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Year</th>
                    <th>Total Amount</th>
                    <th>Payment Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($monthlyCollections as $collection)
                    <tr>
                        <td>{{ \Carbon\Carbon::create()->month($collection->month)->format('F') }}</td>
                        <td>{{ $collection->year }}</td>
                        <td>₱{{ number_format($collection->total, 2) }}</td>
                        <td>{{ $collection->count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No collection data found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
