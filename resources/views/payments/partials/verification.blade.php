@php
    $matchStatus = $payment->reference_match_status ?? 'pending';
    $matchLabels = [
        'matched' => ['MATCHED', 'green', '✓'],
        'mismatched' => ['NOT MATCHED', 'red', '✕'],
        'unreadable' => ['UNABLE TO VERIFY', 'yellow', '!'],
        'duplicate' => ['DUPLICATE', 'red', '✕'],
        'pending' => ['PENDING', 'gray', '…'],
    ];
    [$matchLabel, $matchColor, $matchGlyph] = $matchLabels[$matchStatus] ?? [$matchStatus, 'gray', '…'];
@endphp
<div class="detail-item">
    <span class="detail-label">Receipt Reference (OCR)</span>
    <p class="detail-value">{{ $payment->reference_ocr_result ?? 'N/A' }}</p>
</div>
<div class="detail-item">
    <span class="detail-label">Reference Verification</span>
    <p class="detail-value">
        <span class="badge badge-{{ $matchColor }}">{{ $matchGlyph }} {{ $matchLabel }}</span>
    </p>
    @if(!empty($payment->verification_message))
        <p class="text-muted" style="font-size:.8125rem;">{{ $payment->verification_message }}</p>
    @endif
</div>
