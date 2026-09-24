@extends('layouts.app')

@section('title', 'Add Fee')

@section('content')
<div class="page-header">
    <h2>Add Fee</h2>
    <a href="{{ route('admin.feeAssessment.index') }}" class="btn btn-secondary">Back to Fees</a>
</div>

<div class="form-card">
    <form method="POST" action="{{ route('admin.feeAssessment.store') }}">
        @csrf

        <div class="form-group">
            <label for="fee_name">Fee Name <span class="required">*</span></label>
            <input type="text" name="fee_name" id="fee_name" class="form-control @error('fee_name') is-invalid @enderror" value="{{ old('fee_name') }}" placeholder="Enter fee name" required autofocus>
            @error('fee_name')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="Enter description">{{ old('description') }}</textarea>
            @error('description')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="default_amount">Default Amount (₱) <span class="required">*</span></label>
            <input type="number" name="default_amount" id="default_amount" class="form-control @error('default_amount') is-invalid @enderror" value="{{ old('default_amount', '0.00') }}" step="0.01" min="0" required>
            @error('default_amount')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Fee</button>
            <button type="reset" class="btn btn-secondary">Clear</button>
        </div>
    </form>
</div>
@endsection
