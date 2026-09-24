@extends('layouts.app')

@section('title', 'Cashier Reports')

@section('content')
<div class="page-header">
    <h2>Cashier Reports</h2>
</div>

<div class="filter-bar">
    <form method="GET" action="" class="filter-form" id="reportFilterForm">
        <div class="form-group">
            <label for="date_from">Date From</label>
            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="form-group">
            <label for="date_to">Date To</label>
            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
    </form>
</div>

<div class="reports-grid">
    <a href="{{ route('cashier.reports.daily') }}" class="report-card">
        <div class="report-icon">📅</div>
        <h3>Daily Collections Report</h3>
        <p>View detailed breakdown of collections per day</p>
    </a>

    <a href="{{ route('cashier.reports.summary') }}" class="report-card">
        <div class="report-icon">📊</div>
        <h3>Payment Verification Summary</h3>
        <p>Summary of all payment verifications by status</p>
    </a>

    <a href="{{ route('cashier.reports.daily') }}?status=approved" class="report-card">
        <div class="report-icon">✅</div>
        <h3>Approved Payments Report</h3>
        <p>List of all approved payments for the period</p>
    </a>

    <a href="{{ route('cashier.reports.daily') }}?status=rejected" class="report-card">
        <div class="report-icon">❌</div>
        <h3>Rejected Payments Report</h3>
        <p>List of all rejected payments for the period</p>
    </a>

    <a href="{{ route('cashier.reports.daily') }}?status=pending" class="report-card">
        <div class="report-icon">⏳</div>
        <h3>Pending Payments Report</h3>
        <p>List of all payments awaiting verification</p>
    </a>
</div>

@push('scripts')
<style>
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .report-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 24px;
        text-decoration: none;
        color: inherit;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .report-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .report-icon {
        font-size: 2rem;
        margin-bottom: 12px;
    }
    .report-card h3 {
        margin: 0 0 8px 0;
        font-size: 1.1rem;
    }
    .report-card p {
        margin: 0;
        color: #666;
        font-size: 0.9rem;
    }
</style>
@endpush
@endsection
