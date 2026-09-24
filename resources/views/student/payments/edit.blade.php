@extends('layouts.app')

@section('title', 'Edit Payment')

@section('content')
@php
    $methodLabels = [
        'bank_transfer' => 'Bank Transfer',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'other' => 'Over-the-Counter',
        'cash' => 'Cash',
    ];
@endphp

<div class="page-header">
    <div>
        <h2>Edit Payment #{{ $payment->id }}</h2>
        <p class="page-subtitle">Correct your payment information below.</p>
    </div>
    <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Back to Payments</a>
</div>

@if($errors->any())
    <div class="validation-errors">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($payment->status === 'rejected' && $payment->rejection_reason)
    <div class="alert alert-error">
        <strong>Rejection reason:</strong>&nbsp;{{ $payment->rejection_reason }}
    </div>
@endif

<div class="form-card" style="max-width: 800px;">
    <form method="POST" action="{{ route('student.payments.update', $payment->id) }}" enctype="multipart/form-data" id="editPaymentForm">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="amount">Amount <span class="required">*</span></label>
            <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                   value="{{ old('amount', $payment->amount) }}" step="0.01" min="0.01" required>
            @error('amount')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="payment_method">Payment Method <span class="required">*</span></label>
            <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                <option value="">Select Method</option>
                <option value="bank_transfer" {{ old('payment_method', $payment->payment_method) === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                <option value="gcash" {{ old('payment_method', $payment->payment_method) === 'gcash' ? 'selected' : '' }}>GCash</option>
                <option value="maya" {{ old('payment_method', $payment->payment_method) === 'maya' ? 'selected' : '' }}>Maya</option>
                <option value="other" {{ old('payment_method', $payment->payment_method) === 'other' ? 'selected' : '' }}>Over-the-Counter</option>
            </select>
            @error('payment_method')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="reference_number">Reference Number <span class="required">*</span></label>
            <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                   value="{{ old('reference_number', $payment->reference_number) }}" required maxlength="255">
            @error('reference_number')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="payment_date">Payment Date <span class="required">*</span></label>
            <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                   value="{{ old('payment_date', \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
            @error('payment_date')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                      rows="3" maxlength="1000">{{ old('description', $payment->description) }}</textarea>
            @error('description')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <span class="static-label">Current Proof</span>
            @if($payment->paymentProofs && $payment->paymentProofs->count() > 0)
                @foreach($payment->paymentProofs as $proof)
                    <div class="current-proof">
                        <span>{{ $proof->file_name }}</span>
                        <a href="{{ route('student.payments.proof', [$payment->id, $proof->id]) }}" target="_blank" class="btn btn-sm btn-secondary">View Proof</a>
                    </div>
                @endforeach
            @else
                <p class="text-muted">No proof of payment uploaded.</p>
            @endif
        </div>

        <div class="form-group">
            <label for="proof_of_payment">Replace Proof</label>
            <input type="file" name="proof_of_payment" id="proof_of_payment" class="form-control @error('proof_of_payment') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.pdf">
            @error('proof_of_payment')
                <span class="error-message">{{ $message }}</span>
            @enderror
            <small class="form-help">Leave empty to keep the existing proof. Accepted: JPG, JPEG, PNG, PDF. Max: 5 MB.</small>
            <span class="error-message" id="proofSizeError" style="display:none;">File must not exceed 5 MB.</span>
        </div>

        <div class="form-group image-preview-area" id="imagePreviewArea" style="display: none;">
            <span class="static-label">Preview:</span>
            <div id="imagePreview"></div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Changes</button>
            <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('proof_of_payment');
        var preview = document.getElementById('imagePreview');
        var previewArea = document.getElementById('imagePreviewArea');
        var sizeError = document.getElementById('proofSizeError');
        var form = document.getElementById('editPaymentForm');
        var MAX = 5 * 1024 * 1024;

        if (!input) return;

        input.addEventListener('change', function (e) {
            var file = e.target.files[0];
            preview.innerHTML = '';
            sizeError.style.display = 'none';

            if (!file) { previewArea.style.display = 'none'; return; }

            if (file.size > MAX) {
                sizeError.style.display = 'block';
                input.value = '';
                previewArea.style.display = 'none';
                return;
            }

            previewArea.style.display = 'block';
            if (file.type.indexOf('image/') === 0) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" class="preview-image">';
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                preview.innerHTML = '<p class="pdf-indicator">PDF file selected: ' + file.name + '</p>';
            }
        });

        form.addEventListener('submit', function (e) {
            var file = input.files[0];
            if (file && file.size > MAX) {
                e.preventDefault();
                sizeError.style.display = 'block';
            }
        });
    })();
</script>
<style>
    .page-subtitle { color: var(--text-muted); font-size: .9rem; margin-top: 4px; }
    .current-proof { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f7fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 12px; margin-bottom: 8px; font-size: .875rem; }
    .text-muted { color: var(--text-muted); }
</style>
@endpush
@endsection
