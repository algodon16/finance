<?php

namespace App\Services\Ocr;

interface OcrReaderInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    /**
     * Extract raw text from an image file path.
     * Must never throw for unreadable content — return
     * OcrResult::empty() / OcrResult::error() instead.
     */
    public function read(string $path): OcrResult;
}
