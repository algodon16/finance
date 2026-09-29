<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accountant↔Admin 1:1 alignment: approval workflow columns for assets.
 * asset_status (physical state) is untouched; approval_status is the workflow.
 * Guarded — safe on existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'approval_status')) {
                $table->string('approval_status', 30)->default('draft')->after('asset_status');
            }
            if (!Schema::hasColumn('assets', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('assets', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('assets', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
            if (!Schema::hasColumn('assets', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assets', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('assets', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assets', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assets', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('assets', 'revision_number')) {
                $table->integer('revision_number')->default(0)->after('approval_status');
            }
            if (!Schema::hasColumn('assets', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('assets', 'supporting_document')) {
                $table->string('supporting_document')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        // Workflow columns kept on rollback to avoid data loss.
    }
};
