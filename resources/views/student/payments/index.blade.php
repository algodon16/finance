@extends('layouts.app')

@section('title', 'Payment History')

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
        <h2>Payment History</h2>
        <p class="page-subtitle">View and manage your payment records.</p>
    </div>
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

<div class="payment-grid">
    {{-- MAKE A PAYMENT FORM --}}
    <div class="form-card payment-form-card">
        <h3>Make a Payment</h3>
        <form method="POST" action="{{ route('student.payments.store') }}" enctype="multipart/form-data" id="makePaymentForm">
            @csrf

            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount <span class="required">*</span></label>
                    <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" step="0.01" min="0.01" required placeholder="0.00">
                    @error('amount')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method <span class="required">*</span></label>
                    <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                        <option value="">Select Method</option>
                        <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="gcash" {{ old('payment_method') === 'gcash' ? 'selected' : '' }}>GCash</option>
                        <option value="maya" {{ old('payment_method') === 'maya' ? 'selected' : '' }}>Maya</option>
                        <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Over-the-Counter</option>
                    </select>
                    @error('payment_method')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="reference_number">Reference Number <span class="required">*</span></label>
                    <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror"
                           value="{{ old('reference_number') }}" required placeholder="e.g. BTR-2025-00001" maxlength="255">
                    @error('reference_number')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="payment_date">Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                           value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                    @error('payment_date')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                          rows="3" maxlength="1000" placeholder="Optional note about this payment...">{{ old('description') }}</textarea>
                @error('description')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="proof_of_payment">Proof of Payment <span class="required">*</span></label>
                <input type="file" name="proof_of_payment" id="proof_of_payment" class="form-control @error('proof_of_payment') is-invalid @enderror"
                       accept=".jpg,.jpeg,.png,.pdf" required>
                @error('proof_of_payment')
                    <span class="error-message">{{ $message }}</span>
                @enderror
                <small class="form-help">Accepted formats: JPG, JPEG, PNG, PDF. Maximum file size: 5 MB.</small>
                <span class="error-message" id="proofSizeError" style="display:none;">File must not exceed 5 MB.</span>
            </div>

            <div class="form-group image-preview-area" id="imagePreviewArea" style="display: none;">
                <span class="static-label">Preview:</span>
                <div id="imagePreview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitPaymentBtn">Submit Payment</button>
                <span class="verify-status" id="verifyStatus" style="display:none;">
                    <span class="spinner spinner-sm"></span> Verifying receipt...
                </span>
            </div>
        </form>
    </div>

    {{-- PAYMENT INSTRUCTIONS --}}
    <div class="instructions-card">
        <h3>Payment Instructions</h3>
        <ol class="instructions-list">
            <li>Enter the correct payment details.</li>
            <li>Upload a clear proof of payment.</li>
            <li>Wait for verification from the cashier.</li>
            <li>The student will be notified once the payment is approved or rejected.</li>
        </ol>
        <h4>Accepted Payment Methods</h4>
        <ul class="accepted-methods">
            <li><span class="check-icon">✓</span> Bank Transfer</li>
            <li><span class="check-icon">✓</span> GCash</li>
            <li><span class="check-icon">✓</span> Maya</li>
            <li><span class="check-icon">✓</span> Over-the-Counter</li>
        </ul>
    </div>
</div>

{{-- PAYMENT HISTORY + FILTERS --}}
<div class="history-card">
    <h3>Payment History</h3>

    <div class="filter-bar">
        <form method="GET" action="{{ route('student.payments.index') }}" class="filter-form">
            <div class="form-group">
                <input type="text" name="search" aria-label="Search by reference" placeholder="Search by reference..." value="{{ request('search') }}" class="form-control">
            </div>
            <div class="form-group">
                <select name="status" aria-label="Filter by payment status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>Under Review</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <select name="method" aria-label="Filter by payment method" class="form-control">
                    <option value="">All Methods</option>
                    <option value="bank_transfer" {{ request('method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="gcash" {{ request('method') === 'gcash' ? 'selected' : '' }}>GCash</option>
                    <option value="maya" {{ request('method') === 'maya' ? 'selected' : '' }}>Maya</option>
                    <option value="other" {{ request('method') === 'other' ? 'selected' : '' }}>Over-the-Counter</option>
                </select>
            </div>
            <div class="form-group">
                <input type="date" name="date" value="{{ request('date') }}" class="form-control" title="All Dates">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            @if(request()->hasAny(['search', 'status', 'method', 'date']))
                <a href="{{ route('student.payments.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments ?? [] as $payment)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $methodLabels[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td>
                            @if($payment->status === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($payment->status === 'under_review')
                                <span class="badge badge-blue">Under Review</span>
                            @elseif($payment->status === 'approved')
                                <span class="badge badge-green">Approved</span>
                            @elseif($payment->status === 'rejected')
                                <span class="badge badge-red">Rejected</span>
                            @elseif($payment->status === 'posted')
                                <span class="badge badge-darkgreen">Posted</span>
                            @elseif($payment->status === 'cancelled')
                                <span class="badge badge-gray">Cancelled</span>
                            @else
                                <span class="badge badge-gray">{{ ucfirst($payment->status) }}</span>
                            @endif
                        </td>
                        <td class="actions-cell">
                            <a href="{{ route('student.payments.show', $payment->id) }}" class="btn btn-sm btn-secondary">View</a>
                            @if(in_array($payment->status, ['pending', 'rejected']))
                                <a href="{{ route('student.payments.edit', $payment->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                            @else
                                <button type="button" class="btn btn-sm btn-secondary" disabled title="This payment has already been verified and cannot be edited.">Locked</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No payment records found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($payments) && method_exists($payments, 'links'))
        <div class="pagination">
            {{ $payments->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('proof_of_payment');
        var preview = document.getElementById('imagePreview');
        var previewArea = document.getElementById('imagePreviewArea');
        var sizeError = document.getElementById('proofSizeError');
        var form = document.getElementById('makePaymentForm');
        var MAX = 5 * 1024 * 1024;

        if (!input) return;

        input.addEventListener('change', function (e) {
            var file = e.target.files[0];
            preview.innerHTML = '';
            sizeError.style.display = 'none';

            if (!file) {
                previewArea.style.display = 'none';
                return;
            }

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
                return;
            }
            var btn = document.getElementById('submitPaymentBtn');
            var status = document.getElementById('verifyStatus');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Verifying receipt...';
            }
            if (status) status.style.display = 'inline-flex';
        });
    })();
</script>
<style>
    .page-subtitle { color: var(--text-muted); font-size: .9rem; margin-top: 4px; }
    .payment-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; align-items: start; }
    .payment-form-card { max-width: none; }
    .payment-form-card h3, .instructions-card h3, .history-card h3 { margin-bottom: 16px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .instructions-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); }
    .instructions-list { list-style: decimal; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; color: var(--text); }
    .instructions-card h4 { margin-bottom: 10px; font-size: .9rem; }
    .accepted-methods { display: flex; flex-direction: column; gap: 8px; }
    .accepted-methods li { display: flex; align-items: center; gap: 8px; font-size: .875rem; }
    .check-icon { color: var(--success); font-weight: 700; }
    .history-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); }
    .history-card .filter-bar { box-shadow: none; border: none; padding: 0; margin-bottom: 16px; }
    .actions-cell { display: flex; gap: 8px; align-items: center; }
    .verify-status { display: none; align-items: center; gap: 8px; font-size: .875rem; color: var(--text-muted); }
    @media (max-width: 992px) {
        .payment-grid { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>
@endpush
@endsection
