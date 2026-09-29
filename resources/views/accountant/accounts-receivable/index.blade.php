@extends('layouts.app')

@section('title', 'Accounts Receivable Management')

@section('content')
<div class="page-header">
    <div>
        <h2>Accounts Receivable Management</h2>
        <p class="page-subtitle">Monitor outstanding student balances and receivables.</p>
    </div>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Receivable</h3>
            <p class="card-amount">₱{{ number_format($totalReceivable ?? 0, 2) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Students With Balance</h3>
            <p class="card-amount">{{ number_format($studentsWithBalance ?? 0) }}</p>
        </div>
    </div>
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Overdue Accounts</h3>
            <p class="card-amount">{{ number_format($overdueAccounts ?? 0) }}</p>
        </div>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('accountant.accounts-receivable.index') }}" class="filter-form" style="align-items:flex-end;">
        <div class="form-group" style="flex:0 1 200px;min-width:160px;">
            <label for="search">Search</label>
            <input type="text" name="search" id="search" placeholder="Search student, ID..." value="{{ request('search') }}" class="form-control">
        </div>
        <div class="form-group" style="min-width:150px;">
            <label for="program">Program</label>
            <select name="program" id="program" class="form-control">
                <option value="">All Programs</option>
                @foreach($programs as $program)
                    <option value="{{ $program }}" {{ request('program') === $program ? 'selected' : '' }}>{{ $program }}</option>
                @endforeach
            </select>
        </div>
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
            <label for="academic_year_id">Academic Year</label>
            <select name="academic_year_id" id="academic_year_id" class="form-control">
                <option value="">All Years</option>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}" {{ request('academic_year_id') == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
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
            <label for="balance_status">Balance Status</label>
            <select name="balance_status" id="balance_status" class="form-control">
                <option value="">All</option>
                <option value="with_balance" {{ request('balance_status') === 'with_balance' ? 'selected' : '' }}>With Balance</option>
                <option value="fully_paid" {{ request('balance_status') === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                <option value="overdue" {{ request('balance_status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="{{ route('accountant.accounts-receivable.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Year Level</th>
                    <th>Total Assessment</th>
                    <th>Total Paid</th>
                    <th>Outstanding Balance</th>
                    <th>Due Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->student->student_number ?? 'N/A' }}</td>
                        <td>{{ $account->student->full_name ?? 'N/A' }}</td>
                        <td>{{ $account->student->program ?? 'N/A' }}</td>
                        <td>{{ $account->student->year_level ?? 'N/A' }}</td>
                        <td>₱{{ number_format($account->total_charges ?? 0, 2) }}</td>
                        <td>₱{{ number_format($account->total_paid ?? 0, 2) }}</td>
                        <td><strong>₱{{ number_format($account->outstanding_balance ?? 0, 2) }}</strong></td>
                        <td>{{ $account->due_date ? \Carbon\Carbon::parse($account->due_date)->format('M d, Y') : 'N/A' }}</td>
                        <td><a href="{{ route('accountant.accounts-receivable.show', $account->id) }}" class="btn btn-sm btn-secondary">View Details</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No receivables found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">
        {{ $accounts->links() }}
    </div>
</div>
@endsection
