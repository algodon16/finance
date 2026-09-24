<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Raw OCR text extracted from the uploaded receipt (truncated).
            $table->text('reference_ocr_text')->nullable()->after('reference_number');
            // Reference candidate extracted from the receipt, if any.
            $table->string('reference_ocr_result')->nullable()->after('reference_ocr_text');
            // pending|matched|mismatched|unreadable|duplicate
            $table->string('reference_match_status', 20)->default('pending')->after('reference_ocr_result');
            $table->text('verification_message')->nullable()->after('reference_match_status');
            $table->timestamp('verified_at')->nullable()->after('verification_message');

            $table->index('reference_match_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['reference_match_status']);
            $table->dropColumn([
                'reference_ocr_text',
                'reference_ocr_result',
                'reference_match_status',
                'verification_message',
                'verified_at',
            ]);
        });
    }
};
