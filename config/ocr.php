<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Driver
    |--------------------------------------------------------------------------
    | auto: use tesseract when installed, else OCR.space when OCR_API_KEY
    | is set, else fail safe to manual review.
    | tesseract | ocrspace | none | fake (local/testing only)
    */
    'driver' => env('OCR_DRIVER', 'auto'),

    'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),

    'ocrspace_url' => env('OCRSPACE_URL', 'https://api.ocr.space/parse/image'),

    'ocrspace_key' => env('OCR_API_KEY'),

    'timeout' => env('OCR_TIMEOUT', 60),

    // OCR text shorter than this is treated as unreadable.
    'min_ocr_chars' => env('OCR_MIN_CHARS', 10),

    // Substantial OCR text without the reference means mismatch.
    'mismatch_min_chars' => env('OCR_MISMATCH_MIN_CHARS', 20),

];
