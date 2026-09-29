<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accountant ↔ Admin submission/approval workflow integration.
 *
 * REUSES existing tables (budget_plans, fund_allocations, expenses,
 * accounts_payable, payments) by adding workflow columns only.
 * Creates ONLY the missing tables: financial_requests, reconciliation_records.
 * All operations guarded with hasTable/hasColumn so existing data is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- budget_plans: submission workflow ----
        Schema::table('budget_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_plans', 'justification')) {
                $table->text('justification')->nullable()->after('description');
            }
            if (!Schema::hasColumn('budget_plans', 'funding_source')) {
                $table->string('funding_source')->nullable()->after('justification');
            }
            if (!Schema::hasColumn('budget_plans', 'supporting_document')) {
                $table->string('supporting_document')->nullable()->after('funding_source');
            }
            if (!Schema::hasColumn('budget_plans', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('supporting_document');
            }
            if (!Schema::hasColumn('budget_plans', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('budget_plans', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
            if (!Schema::hasColumn('budget_plans', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('budget_plans', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('budget_plans', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
        });

        // ---- fund_allocations: submission workflow ----
        Schema::table('fund_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('fund_allocations', 'purpose')) {
                $table->string('purpose')->nullable()->after('allocated_to');
            }
            if (!Schema::hasColumn('fund_allocations', 'description')) {
                $table->text('description')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('fund_allocations', 'supporting_document')) {
                $table->string('supporting_document')->nullable()->after('description');
            }
            if (!Schema::hasColumn('fund_allocations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('supporting_document');
            }
            if (!Schema::hasColumn('fund_allocations', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('fund_allocations', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
            if (!Schema::hasColumn('fund_allocations', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('fund_allocations', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('fund_allocations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
        });

        // ---- expenses: submission workflow ----
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'proposed_payment_date')) {
                $table->date('proposed_payment_date')->nullable()->after('expense_date');
            }
            if (!Schema::hasColumn('expenses', 'fund_source')) {
                $table->string('fund_source')->nullable()->after('fund_id');
            }
            if (!Schema::hasColumn('expenses', 'justification')) {
                $table->text('justification')->nullable()->after('description');
            }
            if (!Schema::hasColumn('expenses', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('justification');
            }
            if (!Schema::hasColumn('expenses', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('expenses', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
            if (!Schema::hasColumn('expenses', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('expenses', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
        });

        // ---- accounts_payable: approval workflow ----
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts_payable', 'category')) {
                $table->string('category')->nullable()->after('vendor');
            }
            if (!Schema::hasColumn('accounts_payable', 'description')) {
                $table->text('description')->nullable()->after('category');
            }
            if (!Schema::hasColumn('accounts_payable', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->after('payment_schedule');
            }
            if (!Schema::hasColumn('accounts_payable', 'budget_plan_id')) {
                $table->foreignId('budget_plan_id')->nullable()->after('payment_terms')->constrained('budget_plans')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounts_payable', 'fund_id')) {
                $table->foreignId('fund_id')->nullable()->after('budget_plan_id')->constrained('funds')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounts_payable', 'approval_status')) {
                $table->string('approval_status', 30)->default('draft')->after('payment_status');
            }
            if (!Schema::hasColumn('accounts_payable', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('accounts_payable', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('accounts_payable', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
            if (!Schema::hasColumn('accounts_payable', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('accounts_payable', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounts_payable', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_at');
            }
        });

        // ---- payments: accountant verification + admin decision tracking ----
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'admin_remarks')) {
                $table->text('admin_remarks')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('payments', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('admin_remarks');
            }
        });

        // ---- financial_requests: centralized workflow (NEW — no equivalent exists) ----
        if (!Schema::hasTable('financial_requests')) {
            Schema::create('financial_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number')->unique();
                $table->string('request_type', 50); // budget, fund_allocation, expense, disbursement, payable, assessment_adjustment, reconciliation, other
                $table->string('reference_type')->nullable(); // e.g. App\Models\BudgetPlan
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('department')->nullable();
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->string('supporting_document')->nullable();
                $table->string('status', 30)->default('draft'); // draft, submitted, under_review, approved, rejected, for_revision, completed
                $table->string('admin_decision', 30)->nullable();
                $table->text('admin_remarks')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->index(['request_type', 'status']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // ---- reconciliation_records: prepared by accountant (NEW — currently derived only) ----
        if (!Schema::hasTable('reconciliation_records')) {
            Schema::create('reconciliation_records', function (Blueprint $table) {
                $table->id();
                $table->string('reference_number')->unique();
                $table->date('reconciliation_date');
                $table->string('payment_method')->nullable();
                $table->decimal('system_amount', 15, 2)->default(0);
                $table->decimal('actual_amount', 15, 2)->default(0);
                $table->decimal('variance', 15, 2)->default(0);
                $table->string('reference')->nullable();
                $table->string('status', 30)->default('draft'); // draft, in_progress, reconciled, with_variance, submitted, reviewed
                $table->text('notes')->nullable();
                $table->string('supporting_document')->nullable();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->timestamps();
                $table->index(['status', 'reconciliation_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
        Schema::dropIfExists('financial_requests');
        // Workflow columns on reused tables are intentionally kept on rollback
        // to avoid data loss; drop manually if needed.
    }
};
