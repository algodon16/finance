@extends('layouts.app')

@section('title', 'Student Management')

@section('content')
<div class="page-header">
    <h2>Student Management</h2>
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">+ Add Student</a>
</div>

<div class="filters-bar">
    <form method="GET" action="{{ route('admin.students.index') }}" class="filter-form">
        <div class="filter-group">
            <input type="text" name="search" aria-label="Search by name or student number" class="form-control" placeholder="Search by name or student number..." value="{{ request('search') }}">
        </div>
        <div class="filter-group">
            <input type="text" name="program" aria-label="Filter by program" class="form-control" placeholder="Program..." value="{{ request('program') }}">
        </div>
        <div class="filter-group">
            <select name="year_level" aria-label="Filter by year level" class="form-control">
                <option value="">All Year Levels</option>
                <option value="1" {{ request('year_level') === '1' ? 'selected' : '' }}>1st Year</option>
                <option value="2" {{ request('year_level') === '2' ? 'selected' : '' }}>2nd Year</option>
                <option value="3" {{ request('year_level') === '3' ? 'selected' : '' }}>3rd Year</option>
                <option value="4" {{ request('year_level') === '4' ? 'selected' : '' }}>4th Year</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        @if(request('search') || request('program') || request('year_level'))
            <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Clear</a>
        @endif
    </form>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Student Number</th>
                    <th>Name</th>
                    <th>Program</th>
                    <th>Year</th>
                    <th>Section</th>
                    <th>Clearance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    <tr>
                        <td>{{ $student->student_number }}</td>
                        <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                        <td>{{ $student->program }}</td>
                        <td>{{ $student->year_level }}</td>
                        <td>{{ $student->section }}</td>
                        <td>
                            @if(isset($student->studentAccount))
                                <span class="badge badge-{{ $student->studentAccount->is_cleared ? 'green' : 'red' }}">
                                    {{ $student->studentAccount->is_cleared ? 'Cleared' : 'Not Cleared' }}
                                </span>
                            @else
                                <span class="badge badge-yellow">No Account</span>
                            @endif
                        </td>
                        <td class="actions-cell">
                            <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-secondary">View</a>
                            <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No students found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $students->withQueryString()->links() }}
    </div>
</div>
@endsection
