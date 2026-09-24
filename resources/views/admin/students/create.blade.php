@extends('layouts.app')

@section('title', 'Create Student')

@section('content')
<div class="page-header">
    <h2>Create Student</h2>
    <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Back to Students</a>
</div>

<form method="POST" action="{{ route('admin.students.store') }}">
    @csrf

    <div class="dashboard-card">
        <h3>User Account</h3>

        <div class="form-group">
            <label for="name">Name <span class="required">*</span></label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Student Information</h3>

        <div class="form-row">
            <div class="form-group half">
                <label for="student_number">Student Number <span class="required">*</span></label>
                <input type="text" name="student_number" id="student_number" class="form-control @error('student_number') is-invalid @enderror" value="{{ old('student_number') }}" required>
                @error('student_number')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="program">Program <span class="required">*</span></label>
                <input type="text" name="program" id="program" class="form-control @error('program') is-invalid @enderror" value="{{ old('program') }}" required>
                @error('program')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                @error('first_name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                @error('last_name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="year_level">Year Level <span class="required">*</span></label>
                <select name="year_level" id="year_level" class="form-control @error('year_level') is-invalid @enderror" required>
                    <option value="">Select Year Level</option>
                    <option value="1" {{ old('year_level') == '1' ? 'selected' : '' }}>1st Year</option>
                    <option value="2" {{ old('year_level') == '2' ? 'selected' : '' }}>2nd Year</option>
                    <option value="3" {{ old('year_level') == '3' ? 'selected' : '' }}>3rd Year</option>
                    <option value="4" {{ old('year_level') == '4' ? 'selected' : '' }}>4th Year</option>
                </select>
                @error('year_level')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="section">Section <span class="required">*</span></label>
                <input type="text" name="section" id="section" class="form-control @error('section') is-invalid @enderror" value="{{ old('section') }}" required>
                @error('section')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create Student</button>
        <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
