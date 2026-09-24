@extends('layouts.app')

@section('title', 'Add Fee Assignment')

@section('content')
<div class="page-header">
    <h2>Add Fee Assignment</h2>
    <a href="{{ route('admin.assessmentRules.index') }}" class="btn btn-secondary">Back to Assignments</a>
</div>

<div class="form-card">
    <form method="POST" action="{{ route('admin.assessmentRules.store') }}">
        @csrf

        <div class="form-row">
            <div class="form-group half">
                <label for="program">Program/Course <span class="required">*</span></label>
                <input type="text" name="program" id="program" class="form-control @error('program') is-invalid @enderror" value="{{ old('program') }}" placeholder="e.g. BSIT" required autofocus>
                @error('program')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="year_level">Year Level <span class="required">*</span></label>
                <select name="year_level" id="year_level" class="form-control @error('year_level') is-invalid @enderror" required>
                    <option value="">Select Year Level</option>
                    @foreach(['1st Year', '2nd Year', '3rd Year', '4th Year'] as $year)
                        <option value="{{ $year }}" {{ old('year_level') === $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
                @error('year_level')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="semester">Semester <span class="required">*</span></label>
                <select name="semester" id="semester" class="form-control @error('semester') is-invalid @enderror" required>
                    <option value="">Select Semester</option>
                    @foreach(['1st Semester', '2nd Semester', 'Summer'] as $sem)
                        <option value="{{ $sem }}" {{ old('semester') === $sem ? 'selected' : '' }}>{{ $sem }}</option>
                    @endforeach
                </select>
                @error('semester')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="academic_year">Academic Year <span class="required">*</span></label>
                <input type="text" name="academic_year" id="academic_year" class="form-control @error('academic_year') is-invalid @enderror" value="{{ old('academic_year') }}" placeholder="e.g. 2026-2027" required>
                @error('academic_year')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <span class="static-label">Applicable Fees <span class="required">*</span></span>
            @error('fees')
                <span class="error-message">{{ $message }}</span>
            @enderror
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Fee Name</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fees as $fee)
                            <tr>
                                <td>
                                    <input type="checkbox" name="fees[]" id="fee-{{ $fee->id }}" value="{{ $fee->id }}" {{ in_array($fee->id, old('fees', [])) ? 'checked' : '' }} aria-label="Select {{ $fee->fee_name }}">
                                </td>
                                <td>{{ $fee->fee_name }}</td>
                                <td>₱{{ number_format($fee->default_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">No fees available. <a href="{{ route('admin.feeAssessment.create') }}">Add fees to the catalog first</a>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Assignment</button>
            <button type="reset" class="btn btn-secondary">Clear</button>
        </div>
    </form>
</div>
@endsection
