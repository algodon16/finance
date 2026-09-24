@extends('layouts.app')

@section('title', 'Student Details')

@section('content')
<div class="page-header">
    <h2>Student Details</h2>
    <div>
        <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-secondary">Edit Student</a>
        <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Back to Students</a>
    </div>
</div>

<div class="dashboard-row">
    <div class="dashboard-card">
        <h3>Student Information</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Student Number</span>
                <p>{{ $student->student_number }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <p>{{ $student->first_name }} {{ $student->last_name }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Program</span>
                <p>{{ $student->program }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Year Level</span>
                <p>{{ $student->year_level }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Section</span>
                <p>{{ $student->section }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">User Account</span>
                <p>{{ $student->user->name ?? 'N/A' }} ({{ $student->user->email ?? 'N/A' }})</p>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Financial Summary</h3>
        @if(isset($studentAccount))
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Total Charges</span>
                    <p class="card-amount">₱{{ number_format($studentAccount->total_charges ?? 0, 2) }}</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total Paid</span>
                    <p class="card-amount">₱{{ number_format($studentAccount->total_paid ?? 0, 2) }}</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Outstanding Balance</span>
                    <p class="card-amount">₱{{ number_format($studentAccount->outstanding_balance ?? 0, 2) }}</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Clearance Status</span>
                    <span class="badge badge-{{ $studentAccount->is_cleared ? 'green' : 'red' }}">
                        {{ $studentAccount->is_cleared ? 'Cleared' : 'Not Cleared' }}
                    </span>
                </div>
            </div>
        @else
            <p class="text-muted">No financial account found for this student.</p>
        @endif
    </div>
</div>

@if(isset($studentAccount))
    <div class="dashboard-card">
        <div class="card-footer">
            <a href="{{ route('admin.students.show', $student) }}" class="btn btn-secondary">View Full Account</a>
        </div>
    </div>
@endif
@endsection
