<?php

namespace App\Services\Ocr;

class OcrReaderFactory
{
    public static function make(?string $driver = null): OcrReaderInterface
    {
        $driver = strtolower($driver ?? config('ocr.driver', 'auto'));

        return match ($driver) {
            'tesseract' => new TesseractOcrReader(),
            'ocrspace' => new OcrSpaceReader(),
            'fake' => new FakeOcrReader(),
            'none' => new NullOcrReader(),
            default => self::auto(),
        };
    }

    protected static function auto(): OcrReaderInterface
    {
        $tesseract = new TesseractOcrReader();
        if ($tesseract->isAvailable()) {
            return $tesseract;
        }

        $ocrSpace = new OcrSpaceReader();
        if ($ocrSpace->isAvailable()) {
            return $ocrSpace;
        }

        return new NullOcrReader();
    }
}
