<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP → Expense/Disbursement integration.
 * One AP has at most ONE auto-generated disbursement (unique),
 * and disbursements carry their own payment details (method/reference/date/proof).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('expenses', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('payment_date');
            }
            if (! Schema::hasColumn('expenses', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('expenses', 'proof_of_payment')) {
                $table->string('proof_of_payment', 500)->nullable()->after('payment_reference');
            }
            if (! Schema::hasColumn('expenses', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('proof_of_payment')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paid_by');
            }
        });

        // One AP → at most one disbursement. NULLs (manual proposals) are unaffected (PG allows repeat NULLs).
        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unique('related_payable_id', 'expenses_related_payable_id_unique');
            });
        } catch (\Throwable $e) {
            // Index may already exist — safe to ignore.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropUnique('expenses_related_payable_id_unique');
            });
        } catch (\Throwable $e) {
        }
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['payment_date', 'payment_method', 'payment_reference', 'proof_of_payment', 'paid_by', 'paid_at'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    try { $table->dropColumn($col); } catch (\Throwable $e) {}
                }
            }
        });
    }
};
