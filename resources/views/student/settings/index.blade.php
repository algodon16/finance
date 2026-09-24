@extends('layouts.app')

@section('title', 'Settings')

@section('content')
@php
    $yearLabels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
    $yearLabel = $yearLabels[$student->year_level] ?? ($student->year_level ? 'Year ' . $student->year_level : 'N/A');
    $fullName = trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
@endphp

<div class="page-header">
    <div>
        <h2>Settings</h2>
        <p class="page-subtitle">Manage your account information.</p>
    </div>
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Student Information</h3>
        <span class="badge badge-gray">READ ONLY</span>
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Student ID</span>
            <p class="detail-value">{{ $student->student_number ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Name</span>
            <p class="detail-value">{{ $fullName !== '' ? $fullName : 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Program</span>
            <p class="detail-value">{{ $student->program ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Year Level</span>
            <p class="detail-value">{{ $yearLabel }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Section</span>
            <p class="detail-value">{{ $student->section ?? 'N/A' }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Contact Number</span>
            <p class="detail-value">{{ $student->contact_number ?? 'N/A' }}</p>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3>Email Address</h3>
    <form method="POST" action="{{ route('student.settings.email.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $student->user->email ?? '') }}" required>
            @error('email')
                <span class="error-message">{{ $message }}</span>
            @enderror
            <small class="form-help">This email address will be used to receive payment deadline reminders and important SFMS notifications.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Email</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h3>Change Password</h3>
    <form method="POST" action="{{ route('student.settings.password.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="current_password">Current Password <span class="required">*</span></label>
            <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror"
                   required autocomplete="current-password">
            @error('current_password')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">New Password <span class="required">*</span></label>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                   required autocomplete="new-password">
            @error('password')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm New Password <span class="required">*</span></label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                   required autocomplete="new-password">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
</div>
@endsection
