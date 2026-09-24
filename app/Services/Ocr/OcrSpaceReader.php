<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use Throwable;

class OcrSpaceReader implements OcrReaderInterface
{
    public function name(): string
    {
        return 'ocrspace';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('ocr.ocrspace_key'));
    }

    public function read(string $path): OcrResult
    {
        if (! $this->isAvailable()) {
            return OcrResult::error('OCR API key is not configured.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            return OcrResult::error('Receipt file is not readable.');
        }

        try {
            $response = Http::timeout((int) config('ocr.timeout', 60))
                ->attach('file', file_get_contents($path), basename($path))
                ->post(config('ocr.ocrspace_url'), [
                    'apikey' => config('ocr.ocrspace_key'),
                    'OCREngine' => 2,
                    'scale' => 'true',
                ]);

            if (! $response->successful()) {
                return OcrResult::error('OCR service request failed.');
            }

            $data = $response->json();

            if (! empty($data['IsErroredOnProcessing'])) {
                $message = is_array($data['ErrorMessage'] ?? null)
                    ? implode(' ', $data['ErrorMessage'])
                    : 'OCR service could not process the receipt.';

                return OcrResult::error($message);
            }

            $text = trim((string) ($data['ParsedResults'][0]['ParsedText'] ?? ''));

            return $text === '' ? OcrResult::empty() : OcrResult::ok($text);
        } catch (Throwable $e) {
            return OcrResult::error('OCR service error: ' . $e->getMessage());
        }
    }
}
