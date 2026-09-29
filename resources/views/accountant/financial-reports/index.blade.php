@extends('layouts.app')

@section('title', 'Financial Reporting and Compliance')

@section('content')
<div class="page-header">
    <div>
        <h2>Financial Reporting and Compliance</h2>
        <p class="page-subtitle">Generate and review financial reports. Reconciliation lives in this module.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
    <a href="{{ route('accountant.reconciliation.index') }}" class="btn btn-secondary">Reconciliation</a>
    <a href="{{ route('accountant.reconciliation-records.index') }}" class="btn btn-secondary">Reconciliation Records</a>
    <span class="dash-datetime">{{ now()->format('M d, Y h:i A') }}</span>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('accountant.financial-reports.index') }}" class="filter-form" id="reportForm" style="align-items:flex-end;">
        <div class="filter-rows">
            <div class="filter-row">
                <div class="form-group report-type-group" style="flex:0 1 220px;min-width:180px;">
                    <label for="report_type">Report Type</label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="">Select Report</option>
                        <option value="daily_collection" {{ request('report_type') === 'daily_collection' ? 'selected' : '' }}>Daily Collection Report</option>
                        <option value="monthly_collection" {{ request('report_type') === 'monthly_collection' ? 'selected' : '' }}>Monthly Collection Report</option>
                        <option value="payment_summary" {{ request('report_type') === 'payment_summary' ? 'selected' : '' }}>Payment Summary Report</option>
                        <option value="accounts_receivable" {{ request('report_type') === 'accounts_receivable' ? 'selected' : '' }}>Accounts Receivable Report</option>
                        <option value="outstanding_balance" {{ request('report_type') === 'outstanding_balance' ? 'selected' : '' }}>Outstanding Balance Report</option>
                        <option value="reconciliation" {{ request('report_type') === 'reconciliation' ? 'selected' : '' }}>Reconciliation Report</option>
                    </select>
                </div>
                <div class="form-group" style="min-width:150px;">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="form-group" style="min-width:150px;">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="form-group" style="min-width:160px;">
                    <label for="program">Program</label>
                    <select name="program" id="program" class="form-control">
                        <option value="">All Programs</option>
                        @foreach($programs as $program)
                            <option value="{{ $program }}" {{ request('program') === $program ? 'selected' : '' }}>{{ $program }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="form-group" style="min-width:150px;">
                    <label for="year_level">Year Level</label>
                    <select name="year_level" id="year_level" class="form-control">
                        <option value="">All Year Levels</option>
                        @foreach($yearLevels as $level)
                            <option value="{{ $level }}" {{ request('year_level') == $level ? 'selected' : '' }}>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="min-width:150px;">
                    <label for="semester_id">Semester</label>
                    <select name="semester_id" id="semester_id" class="form-control">
                        <option value="">All Semesters</option>
                        @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>{{ $semester->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="min-width:150px;">
                    <label for="academic_year_id">Academic Year</label>
                    <select name="academic_year_id" id="academic_year_id" class="form-control">
                        <option value="">All Years</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ request('academic_year_id') == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="min-width:150px;">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="">All Methods</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method }}" {{ request('payment_method') === $method ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-secondary">Generate Report</button>
            <a href="{{ route('accountant.financial-reports.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

@if($report)
<div class="dashboard-card report-output" id="reportOutput">
    <div class="report-header">
        <h4 class="report-title">{{ $report['title'] }}</h4>
        <p class="report-meta">
            Date Range: {{ $report['date_from'] ? \Carbon\Carbon::parse($report['date_from'])->format('M d, Y') : 'Beginning' }} to {{ $report['date_to'] ? \Carbon\Carbon::parse($report['date_to'])->format('M d, Y') : 'Present' }}
        </p>
        <p class="report-meta">Generated: {{ $report['generated_date'] }} | Generated By: {{ $report['generated_by'] }}</p>
    </div>

    @if(!empty($report['error']))
        <div class="alert alert-error">{{ $report['error'] }}</div>
    @endif

    @if(count($report['summary']))
    <div class="report-summary">
        @foreach($report['summary'] as $label => $value)
            <div class="report-summary-item">
                <span class="detail-label">{{ $label }}</span>
                <p class="detail-value">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    @endif

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    @foreach($report['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($report['columns']) }}" class="text-center">
                            No recorded payments found for the selected criteria.
                            @if(count($report['applied_filters'] ?? []))
                                <br>Active filters: {{ implode(' | ', $report['applied_filters']) }}
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        <button type="button" class="btn btn-success" onclick="window.print()">Export PDF</button>
    </div>
    <p class="form-help">Export PDF uses your browser's Print to PDF destination.</p>
</div>
@else
<div class="dashboard-card">
    <h3>Report Types</h3>
    <ul class="report-type-list">
        <li>Daily Collection Report</li>
        <li>Monthly Collection Report</li>
        <li>Payment Summary Report</li>
        <li>Accounts Receivable Report</li>
        <li>Outstanding Balance Report</li>
        <li>Reconciliation Report</li>
    </ul>
    <p class="form-help">Select a report type and date range, then click Generate Report.</p>
</div>
@endif
@endsection
