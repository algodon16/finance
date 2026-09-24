<?php

namespace App\Services\Ocr;

class OcrResult
{
    public const OK = 'ok';
    public const EMPTY = 'empty';
    public const ERROR = 'error';

    public function __construct(
        public readonly string $status,
        public readonly ?string $text = null,
        public readonly ?string $message = null
    ) {}

    public static function ok(string $text): self
    {
        return new self(self::OK, $text);
    }

    public static function empty(string $message = 'No readable text found.'): self
    {
        return new self(self::EMPTY, null, $message);
    }

    public static function error(string $message): self
    {
        return new self(self::ERROR, null, $message);
    }

    public function hasText(): bool
    {
        return $this->status === self::OK && trim((string) $this->text) !== '';
    }
}
