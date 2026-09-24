@extends('layouts.app')

@section('title', 'Request Details')

@section('content')
@php
    $status = $procurementRequest->status;
    $statusLabel = ucfirst(str_replace('_', ' ', $status));
    $canPrint = in_array(strtolower($status ?? ''), ['approved', 'released', 'completed']);
@endphp

<div class="print-only print-header">
    <p class="print-brand">SFMS</p>
    <h2>REQUEST DETAILS</h2>
</div>

<div class="page-header screen-only">
    <div>
        <h2>Request Details</h2>
        <p class="page-subtitle">View the details of your procurement request.</p>
    </div>
    <div class="header-actions-wrapper">
        <div class="header-actions print-zone">
            <a href="{{ route('student.procurement.index') }}" class="btn btn-secondary">Back</a>
            @if($canPrint)
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            @else
                <button type="button" class="btn btn-primary print-disabled" aria-disabled="true" data-print-disabled="true">Print</button>
                <div class="print-notice" id="printNotice" hidden>Printing is unavailable. Your request must be approved by the cashier first.</div>
            @endif
        </div>
    </div>
</div>

<div class="print-only print-summary">
    <p><strong>Request Number:</strong> #{{ $procurementRequest->id }}</p>
    <p><strong>Status:</strong> {{ $statusLabel }}</p>
    <p><strong>Submitted On:</strong> {{ \Carbon\Carbon::parse($procurementRequest->created_at)->format('F d, Y h:i A') }}</p>
    <p><strong>Last Updated:</strong> {{ \Carbon\Carbon::parse($procurementRequest->updated_at)->format('F d, Y h:i A') }}</p>
    <p><strong>Total Amount:</strong> ₱{{ number_format($procurementRequest->total_amount, 2) }}</p>
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Request #{{ $procurementRequest->id }}</h3>
        @if(in_array($status, ['submitted', 'pending_approval']))
            <span class="badge badge-yellow">{{ $statusLabel }}</span>
        @elseif($status === 'approved')
            <span class="badge badge-green">Approved</span>
        @elseif($status === 'rejected')
            <span class="badge badge-red">Rejected</span>
        @else
            <span class="badge badge-blue">{{ $statusLabel }}</span>
        @endif
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Request ID</span>
            <p class="detail-value">#{{ $procurementRequest->id }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Submitted On</span>
            <p class="detail-value">{{ \Carbon\Carbon::parse($procurementRequest->created_at)->format('F d, Y h:i A') }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Last Updated</span>
            <p class="detail-value">{{ \Carbon\Carbon::parse($procurementRequest->updated_at)->format('F d, Y h:i A') }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Total Amount</span>
            <p class="detail-value">₱{{ number_format($procurementRequest->total_amount, 2) }}</p>
        </div>
        <div class="detail-item">
            <span class="detail-label">Status</span>
            <p class="detail-value">{{ $statusLabel }}</p>
        </div>
    </div>
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Additional Information</h3>
    </div>
    @php
        $reviewerName = $procurementRequest->reviewer->name ?? null;
        $reviewedAt = $procurementRequest->reviewed_at
            ? \Carbon\Carbon::parse($procurementRequest->reviewed_at)->format('F d, Y h:i A')
            : null;
        $hasAdditional = !empty($procurementRequest->remarks) || !empty($reviewerName) || !empty($reviewedAt);
    @endphp
    @if($hasAdditional)
        <dl class="info-list">
            <div class="info-row">
                <dt>Remarks</dt>
                <dd>{{ $procurementRequest->remarks ?? '-' }}</dd>
            </div>
            <div class="info-row">
                <dt>Reviewed By</dt>
                <dd>{{ $reviewerName ?? '-' }}</dd>
            </div>
            <div class="info-row">
                <dt>Reviewed At</dt>
                <dd>{{ $reviewedAt ?? '-' }}</dd>
            </div>
        </dl>
    @else
        <p class="empty-note">No additional information provided.</p>
    @endif
</div>

<div class="detail-card">
    <div class="detail-header">
        <h3>Requested Items</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Size</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($procurementRequest->items as $requestItem)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $requestItem->item->item_name ?? 'N/A' }}</td>
                        <td>{{ $requestItem->size ?? '-' }}</td>
                        <td>{{ $requestItem->quantity ?? 'N/A' }}</td>
                        <td>₱{{ number_format($requestItem->unit_price ?? $requestItem->item->price ?? 0, 2) }}</td>
                        <td>₱{{ number_format($requestItem->subtotal ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No items in this request.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="total-label"><strong>Total</strong></td>
                    <td><strong>₱{{ number_format($procurementRequest->total_amount, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var timer = null;
        document.addEventListener('click', function (event) {
            var printButton = event.target.closest('[data-print-disabled="true"]');
            if (!printButton) {
                return;
            }
            event.preventDefault();
            var notice = document.getElementById('printNotice');
            if (!notice) {
                return;
            }
            notice.hidden = false;
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                notice.hidden = true;
            }, 4000);
        });
    })();
</script>
<style>
    .info-list { display: flex; flex-direction: column; padding: 6px 24px 24px; }
    .info-row { display: grid; grid-template-columns: 180px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
    .info-row:last-child { border-bottom: none; }
    .info-row dt { font-weight: 600; font-size: .875rem; color: var(--text-muted); }
    .info-row dd { font-size: .875rem; margin: 0; }
    .total-label { text-align: right; }
    .header-actions-wrapper { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
    .page-header { align-items: flex-start; gap: 20px; }
    .header-actions { align-items: center; justify-content: flex-end; white-space: nowrap; }
    .print-zone { position: relative; }
    .print-disabled { background-color: #E5E7EB; border-color: #E5E7EB; color: #9CA3AF; opacity: 0.8; box-shadow: none; cursor: not-allowed; }
    .print-notice { position: absolute; right: 0; top: calc(100% + 8px); width: max-content; max-width: 320px; padding: 8px 12px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 6px; font-size: 12px; line-height: 1.4; box-shadow: 0 2px 8px rgba(0,0,0,0.08); z-index: 100; white-space: normal; text-align: left; }
    .print-only { display: none; }
    @media print {
        .sidebar, .top-bar, .screen-only, .sidebar-overlay,
        .alert, .validation-errors { display: none !important; }
        .main-content { margin-left: 0 !important; }
        .content-area { padding: 0 !important; }
        .detail-card { box-shadow: none !important; break-inside: avoid; margin-bottom: 16px; }
        .print-only { display: block !important; }
        .print-summary { display: block !important; margin-bottom: 16px; font-size: .9rem; line-height: 1.7; }
        .print-header { text-align: center; margin-bottom: 20px; }
        .print-header .print-brand { font-size: 1.4rem; font-weight: 700; }
        .print-header .print-sub { color: #555; margin-bottom: 8px; }
        .print-footer { text-align: center; margin-top: 24px; font-size: .8rem; color: #555; }
        .btn { display: none !important; }
    }
    @media (max-width: 640px) {
        .info-row { grid-template-columns: 1fr; gap: 2px; }
    }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; }
        .header-actions-wrapper { width: 100%; align-items: flex-start; }
        .header-actions { justify-content: flex-start; }
        .print-notice { text-align: left; }
    }
</style>
@endpush
@endsection
