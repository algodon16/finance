@extends('layouts.app')

@section('title', 'Submit Payment')

@section('content')
<div class="page-header">
    <h2>Submit Payment</h2>
    <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Back to Payments</a>
</div>

<div class="form-card">
    <form method="POST" action="{{ route('student.payments.store') }}" enctype="multipart/form-data">
        @csrf
        @method('POST')

        <div class="form-group">
            <label for="amount">Amount <span class="required">*</span></label>
            <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                   value="{{ old('amount') }}" step="0.01" min="0.01" required>
            @error('amount')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="payment_method">Payment Method <span class="required">*</span></label>
            <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                <option value="">Select Method</option>
                <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                <option value="gcash" {{ old('payment_method') === 'gcash' ? 'selected' : '' }}>GCash</option>
                <option value="maya" {{ old('payment_method') === 'maya' ? 'selected' : '' }}>Maya</option>
                <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Other</option>
            </select>
            @error('payment_method')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="reference_number">Reference Number</label>
            <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                   value="{{ old('reference_number') }}">
            @error('reference_number')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="payment_date">Payment Date <span class="required">*</span></label>
            <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                   value="{{ old('payment_date', date('Y-m-d')) }}" required>
            @error('payment_date')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                      rows="3">{{ old('description') }}</textarea>
            @error('description')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="proof_of_payment">Proof of Payment</label>
            <input type="file" name="proof_of_payment" id="proof_of_payment" class="form-control @error('proof_of_payment') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.pdf">
            @error('proof_of_payment')
                <span class="error-message">{{ $message }}</span>
            @enderror
            <small class="form-help">Accepted formats: JPG, JPEG, PNG, PDF</small>
        </div>

        <div class="form-group image-preview-area" id="imagePreviewArea" style="display: none;">
            <span class="static-label">Preview:</span>
            <div id="imagePreview"></div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Payment</button>
            <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.getElementById('proof_of_payment').addEventListener('change', function(e) {
        var preview = document.getElementById('imagePreview');
        var previewArea = document.getElementById('imagePreviewArea');
        var file = e.target.files[0];

        if (file) {
            previewArea.style.display = 'block';
            preview.innerHTML = '';

            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(event) {
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" class="preview-image">';
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                preview.innerHTML = '<p class="pdf-indicator">📄 PDF file selected: ' + file.name + '</p>';
            }
        } else {
            previewArea.style.display = 'none';
            preview.innerHTML = '';
        }
    });
</script>
@endpush
@endsection
