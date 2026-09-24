@extends('layouts.app')

@section('title', 'Accounts Receivable')

@section('content')
<div class="page-header">
    <h2>Accounts Receivable</h2>
</div>

<div class="summary-cards">
    <div class="dashboard-card card-blue">
        <div class="card-content">
            <h3>Total Receivables</h3>
            <p class="card-amount">₱{{ number_format($totalReceivables ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-red">
        <div class="card-content">
            <h3>Total Charges</h3>
            <p class="card-amount">₱{{ number_format($totalCharges ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card card-green">
        <div class="card-content">
            <h3>Total Collected</h3>
            <p class="card-amount">₱{{ number_format($totalCollected ?? 0, 2) }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student Number</th>
                    <th>Student Name</th>
                    <th>Total Charges</th>
                    <th>Total Paid</th>
                    <th>Outstanding Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->student->student_number ?? 'N/A' }}</td>
                        <td>{{ $account->student->first_name ?? '' }} {{ $account->student->last_name ?? '' }}</td>
                        <td>₱{{ number_format($account->total_charges ?? 0, 2) }}</td>
                        <td>₱{{ number_format($account->total_paid ?? 0, 2) }}</td>
                        <td>₱{{ number_format($account->outstanding_balance ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No accounts found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">
        {{ $accounts->links() }}
    </div>
</div>
@endsection
