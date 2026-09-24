@extends('layouts.app')

@section('title', 'Payment Verification - Details')

@section('content')
<div class="page-header">
    <h2>Payment Verification - Details</h2>
    <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary">Back to Payments</a>
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Payment #{{ $payment->id }}</h3>
        @if($payment->status === 'pending')
            <span class="badge badge-yellow">Pending</span>
        @elseif($payment->status === 'under_review')
            <span class="badge badge-blue">Under Review</span>
        @elseif($payment->status === 'approved')
            <span class="badge badge-green">Approved</span>
        @else
            <span class="badge badge-red">Rejected</span>
        @endif
    </div>

    <div class="detail-grid-2col">
        <div class="detail-col">
            <h4>Payment Information</h4>
            <div class="detail-item">
                <span class="detail-label">Amount</span>
                <p class="detail-value">₱{{ number_format($payment->amount, 2) }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Method</span>
                <p class="detail-value">{{ $payment->payment_method }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Date</span>
                <p class="detail-value">{{ date('M d, Y', strtotime($payment->payment_date)) }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Reference Number</span>
                <p class="detail-value">{{ $payment->reference_number ?? 'N/A' }}</p>
            </div>
            @include('payments.partials.verification', ['payment' => $payment])
            <div class="detail-item">
                <span class="detail-label">Description</span>
                <p class="detail-value">{{ $payment->description ?? 'No description' }}</p>
            </div>

            <h4>Student Information</h4>
            <div class="detail-item">
                <span class="detail-label">Student Name</span>
                <p class="detail-value">{{ $payment->student->full_name ?? 'N/A' }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Student ID</span>
                <p class="detail-value">{{ $payment->student->student_number ?? 'N/A' }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Program/Course</span>
                <p class="detail-value">{{ $payment->student->program ?? 'N/A' }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Year Level</span>
                <p class="detail-value">{{ $payment->student->year_level ?? 'N/A' }}</p>
            </div>

            <h4>Assessment / Balance Information</h4>
            <div class="detail-item">
                <span class="detail-label">Total Charges</span>
                <p class="detail-value">₱{{ number_format($payment->student->studentAccount->total_charges ?? 0, 2) }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total Paid</span>
                <p class="detail-value">₱{{ number_format($payment->student->studentAccount->total_paid ?? 0, 2) }}</p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Outstanding Balance</span>
                <p class="detail-value">₱{{ number_format($payment->student->studentAccount->outstanding_balance ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="detail-col">
            <h4>Proof of Payment</h4>
            @if(isset($payment->paymentProofs) && $payment->paymentProofs->count() > 0)
                @foreach($payment->paymentProofs as $proof)
                    <div class="proof-section">
                        @if(in_array(strtolower(pathinfo($proof->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <div class="proof-image">
                                <img src="{{ asset('storage/' . $proof->file_path) }}" alt="Proof of Payment" class="proof-image-tag">
                            </div>
                        @else
                            <a href="{{ asset('storage/' . $proof->file_path) }}" target="_blank" class="btn btn-secondary">
                                View PDF
                            </a>
                        @endif
                    </div>
                @endforeach
            @elseif($payment->proof_of_payment)
                <div class="proof-section">
                    @if(in_array(strtolower(pathinfo($payment->proof_of_payment, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                        <div class="proof-image">
                            <img src="{{ asset('storage/' . $payment->proof_of_payment) }}" alt="Proof of Payment" class="proof-image-tag">
                        </div>
                    @else
                        <a href="{{ asset('storage/' . $payment->proof_of_payment) }}" target="_blank" class="btn btn-secondary">
                            View PDF
                        </a>
                    @endif
                </div>
            @else
                <p class="text-muted">No proof of payment uploaded</p>
            @endif
        </div>
    </div>

    @if($payment->status === 'rejected' && $payment->rejection_reason)
        <div class="rejection-reason">
            <h4>Rejection Reason</h4>
            <p>{{ $payment->rejection_reason }}</p>
        </div>
    @endif

    @if(in_array($payment->status, ['pending', 'under_review']))
        <div class="action-buttons">
            <form method="POST" action="{{ route('cashier.payments.approve', $payment->id) }}" class="inline-form">
                @csrf
                @method('POST')
                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this payment?')">
                    Approve Payment
                </button>
            </form>

            <button type="button" class="btn btn-danger" onclick="document.getElementById('rejectModal').style.display='flex'">
                Reject Payment
            </button>
        </div>
    @endif
</div>

<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Reject Payment</h3>
            <button type="button" class="modal-close" aria-label="Close" onclick="document.getElementById('rejectModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="{{ route('cashier.payments.reject', $payment->id) }}">
            @csrf
            @method('POST')
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection</label>
                    <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" required placeholder="Enter the reason for rejecting this payment..."></textarea>
                    @error('rejection_reason')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .modal {
        background: #fff;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e0e0e0;
    }
    .modal-header h3 { margin: 0; }
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }
    .modal-body { padding: 20px; }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 16px 20px;
        border-top: 1px solid #e0e0e0;
    }
    .inline-form { display: inline-block; }
    .action-buttons {
        display: flex;
        gap: 8px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }
    .error-text { color: #dc3545; font-size: 0.85rem; }
</style>
@endpush
@endsection
