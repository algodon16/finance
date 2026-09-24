<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Allow admin-created procurement requests without a linked student.
 * Existing rows are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop FK, alter nullability, re-add FK (PostgreSQL compatible).
        try {
            Schema::table('procurement_requests', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
            });
        } catch (\Throwable $e) {
        }
        DB::statement('ALTER TABLE procurement_requests ALTER COLUMN student_id DROP NOT NULL');
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('UPDATE procurement_requests SET student_id = (SELECT id FROM students LIMIT 1) WHERE student_id IS NULL');
        DB::statement('ALTER TABLE procurement_requests ALTER COLUMN student_id SET NOT NULL');
    }
};
