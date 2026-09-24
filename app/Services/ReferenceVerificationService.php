<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Ocr\OcrReaderFactory;
use App\Services\Ocr\OcrReaderInterface;
use Illuminate\Http\UploadedFile;
use Throwable;

class ReferenceVerificationService
{
    public const PENDING = 'pending';
    public const MATCHED = 'matched';
    public const MISMATCHED = 'mismatched';
    public const UNREADABLE = 'unreadable';
    public const DUPLICATE = 'duplicate';

    protected OcrReaderInterface $reader;

    public function __construct(?OcrReaderInterface $reader = null)
    {
        $this->reader = $reader ?? OcrReaderFactory::make();
    }

    /**
     * Normalize a reference number for comparison.
     * Uppercase + strip ALL whitespace (spaces, line breaks, tabs).
     * Dashes, digits and letters are meaningful and always kept.
     */
    public static function normalize(?string $reference): string
    {
        if ($reference === null) {
            return '';
        }

        $clean = preg_replace('/\s+/u', '', $reference);

        return strtoupper(trim((string) $clean));
    }

    /**
     * Compare student input against OCR text without ever guessing.
     * Returns status + extracted candidate + human message.
     */
    public function verifyWithText(?string $input, ?string $ocrText): array
    {
        $normalizedInput = self::normalize($input);

        if ($normalizedInput === '') {
            return $this->result(self::MISMATCHED, null, 'Payment was not accepted. The reference number does not match the uploaded receipt.', $ocrText);
        }

        $text = trim((string) $ocrText);

        if ($text === '' || mb_strlen($text) < (int) config('ocr.min_ocr_chars', 10)) {
            return $this->result(self::UNREADABLE, null, 'Payment submitted for manual review. We could not reliably read the reference number from the receipt.', $ocrText);
        }

        // Uncertain OCR output (wildcards/replacement chars) must never match.
        if (str_contains($text, '?') || str_contains($text, "\u{FFFD}")) {
            return $this->result(self::UNREADABLE, null, 'Payment submitted for manual review. We could not reliably read the reference number from the receipt.', $ocrText);
        }

        $candidate = $this->findCandidate($normalizedInput, $text);

        if ($candidate !== null) {
            return $this->result(self::MATCHED, $candidate, 'Reference number verified.', $ocrText);
        }

        if (mb_strlen($text) >= (int) config('ocr.mismatch_min_chars', 20)) {
            return $this->result(self::MISMATCHED, null, 'Payment was not accepted. The reference number does not match the uploaded receipt.', $ocrText);
        }

        return $this->result(self::UNREADABLE, null, 'Payment submitted for manual review. We could not reliably read the reference number from the receipt.', $ocrText);
    }

    /**
     * Find the input reference inside OCR text: exact token match,
     * or containment in whitespace-stripped OCR text (receipts often
     * split references across lines/spaces).
     */
    protected function findCandidate(string $normalizedInput, string $ocrText): ?string
    {
        $upper = strtoupper($ocrText);

        $tokens = preg_split('/[^A-Z0-9\-]+/', $upper, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if ($token === $normalizedInput) {
                return $token;
            }
        }

        $despaced = preg_replace('/\s+/u', '', $upper);

        if ($despaced && str_contains($despaced, $normalizedInput)) {
            return $normalizedInput;
        }

        return null;
    }

    /**
     * Check whether the normalized reference is already used by another
     * live payment (pending/under_review/approved/posted). Rejected and
     * cancelled payments do not block reuse.
     */
    public function isDuplicateReference(string $reference, ?int $excludeId = null): bool
    {
        $normalized = self::normalize($reference);

        if ($normalized === '') {
            return false;
        }

        $query = Payment::whereNotIn('status', ['rejected', 'cancelled'])
            ->whereNotNull('reference_number');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        foreach ($query->pluck('reference_number') as $existing) {
            if (self::normalize($existing) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run OCR on an uploaded receipt file. Never throws — any failure
     * (unsupported type, missing engine, transport error) degrades to
     * UNREADABLE so the payment goes to manual review, never auto-pass.
     */
    public function verifyUpload(UploadedFile $file, string $inputReference): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        if ($extension === 'pdf' || $mime === 'application/pdf') {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }

        try {
            $ocr = $this->reader->read($file->getRealPath());

            if (! $ocr->hasText()) {
                return $this->result(
                    self::UNREADABLE,
                    null,
                    'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
                );
            }

            return $this->verifyWithText($inputReference, $ocr->text);
        } catch (Throwable) {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }
    }

    /**
     * Re-run verification against an already-stored proof file
     * (e.g. reference changed without a new upload).
     */
    public function verifyStoredFile(string $absolutePath, string $inputReference): array
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }

        try {
            $ocr = $this->reader->read($absolutePath);

            if (! $ocr->hasText()) {
                return $this->result(
                    self::UNREADABLE,
                    null,
                    'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
                );
            }

            return $this->verifyWithText($inputReference, $ocr->text);
        } catch (Throwable) {
            return $this->result(
                self::UNREADABLE,
                null,
                'Payment submitted for manual review. We could not reliably read the reference number from the receipt.'
            );
        }
    }

    protected function result(string $status, ?string $extracted, string $message, ?string $ocrText = null): array
    {
        return [
            'status' => $status,
            'extracted' => $extracted,
            'message' => $message,
            'ocrText' => $ocrText !== null && trim($ocrText) !== '' ? mb_substr($ocrText, 0, 4000) : null,
        ];
    }
}
