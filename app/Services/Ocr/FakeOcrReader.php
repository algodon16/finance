<?php

namespace App\Services\Ocr;

use RuntimeException;

/**
 * Deterministic stand-in for local development and automated tests.
 * Refuses to run in production so canned text can never verify
 * a real payment.
 */
class FakeOcrReader implements OcrReaderInterface
{
    public function __construct(protected ?string $text = null)
    {
        // Test seam: canned receipt text can also come from the
        // OCR_FAKE_TEXT environment variable (local/testing only).
        if ($this->text === null) {
            $envText = env('OCR_FAKE_TEXT');
            if (is_string($envText) && trim($envText) !== '') {
                $this->text = $envText;
            }
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function read(string $path): OcrResult
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Fake OCR reader is not allowed in production.');
        }

        if ($this->text === null || trim($this->text) === '') {
            return OcrResult::empty();
        }

        return OcrResult::ok($this->text);
    }
}
