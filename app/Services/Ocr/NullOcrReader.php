<?php

namespace App\Services\Ocr;

/**
 * Safe fallback when no OCR engine is available.
 * Always reports unreadable so payments fall back to
 * manual cashier review — never auto-approved.
 */
class NullOcrReader implements OcrReaderInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function read(string $path): OcrResult
    {
        return OcrResult::error('Automatic receipt verification is unavailable. Manual review required.');
    }
}
