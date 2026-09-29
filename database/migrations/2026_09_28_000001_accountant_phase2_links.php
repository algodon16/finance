<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: line items, record linking (no duplicates), revision tracking.
 * All guarded — safe on existing Supabase data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- budget line items (NEW — no equivalent exists) ----
        if (!Schema::hasTable('budget_plan_items')) {
            Schema::create('budget_plan_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_plan_id')->constrained('budget_plans')->cascadeOnDelete();
                $table->string('item_name');
                $table->string('category')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);
                $table->text('justification')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('budget_plan_id');
            });
        }

        // ---- revision + submitter/reviewer tracking on workflow tables ----
        $tracked = ['budget_plans', 'fund_allocations', 'expenses', 'accounts_payable', 'financial_requests', 'reconciliation_records'];
        foreach ($tracked as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'revision_number')) {
                    $table->integer('revision_number')->default(0)->after('status');
                }
                if (!Schema::hasColumn($tbl, 'submitted_by')) {
                    $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn($tbl, 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn($tbl, 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('reviewed_by');
                }
            });
        }

        // ---- link expense -> fund allocation (downstream, no duplicate) ----
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'fund_allocation_id')) {
                $table->foreignId('fund_allocation_id')->nullable()->after('fund_id')->constrained('fund_allocations')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'related_payable_id')) {
                $table->foreignId('related_payable_id')->nullable()->after('fund_allocation_id')->constrained('accounts_payable')->nullOnDelete();
            }
        });

        // ---- link payable -> expense + allocation (FK, not copied data) ----
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts_payable', 'expense_id')) {
                $table->foreignId('expense_id')->nullable()->after('fund_id')->constrained('expenses')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounts_payable', 'fund_allocation_id')) {
                $table->foreignId('fund_allocation_id')->nullable()->after('expense_id')->constrained('fund_allocations')->nullOnDelete();
            }
        });

        // ---- fund allocation already links fund+budget; add purpose index ----
        if (Schema::hasTable('fund_allocations') && !Schema::hasColumn('fund_allocations', 'budget_plan_id')) {
            Schema::table('fund_allocations', function (Blueprint $table) {
                $table->foreignId('budget_plan_id')->nullable()->constrained('budget_plans')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_items');
    }
};
