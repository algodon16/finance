<?php

namespace App\Services\Ocr;

use Symfony\Component\Process\Process;
use Throwable;

class TesseractOcrReader implements OcrReaderInterface
{
    protected ?bool $available = null;

    public function name(): string
    {
        return 'tesseract';
    }

    public function isAvailable(): bool
    {
        if ($this->available === null) {
            try {
                $process = new Process([$this->binary(), '--version']);
                $process->setTimeout(10);
                $process->run();
                $this->available = $process->isSuccessful();
            } catch (Throwable) {
                $this->available = false;
            }
        }

        return $this->available;
    }

    public function read(string $path): OcrResult
    {
        if (! $this->isAvailable()) {
            return OcrResult::error('Tesseract OCR is not installed.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            return OcrResult::error('Receipt file is not readable.');
        }

        try {
            $process = new Process([$this->binary(), $path, 'stdout', '--psm', '6', '-l', 'eng']);
            $process->setTimeout((int) config('ocr.timeout', 60));
            $process->run();

            if (! $process->isSuccessful()) {
                return OcrResult::error('OCR engine failed to process the receipt.');
            }

            $text = trim($process->getOutput());

            return $text === '' ? OcrResult::empty() : OcrResult::ok($text);
        } catch (Throwable $e) {
            return OcrResult::error('OCR engine error: ' . $e->getMessage());
        }
    }

    protected function binary(): string
    {
        return config('ocr.tesseract_path', 'tesseract');
    }
}
