<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BCP Financial Management System — admin module tables.
 *
 * Guarded with hasTable/hasColumn checks so it is safe to run against the
 * existing Supabase database without touching working tables/records.
 * All monetary columns use NUMERIC (decimal) — never float.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Budget plans (institutional) ----
        if (!Schema::hasTable('budget_plans')) {
            Schema::create('budget_plans', function (Blueprint $table) {
                $table->id();
                $table->string('budget_name');
                $table->string('fiscal_year', 20);
                $table->string('department')->nullable();
                $table->string('budget_category');
                $table->decimal('allocated_amount', 15, 2);
                $table->decimal('utilized_amount', 15, 2)->default(0);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 30)->default('active');
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['fiscal_year', 'status']);
            });
        }

        // ---- Budget allocations ----
        if (!Schema::hasTable('budget_allocations')) {
            Schema::create('budget_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_plan_id')->constrained('budget_plans')->cascadeOnDelete();
                $table->string('allocation_type', 50); // department, program, scholarship, operational, academic
                $table->string('allocated_to')->nullable(); // department/program name
                $table->decimal('amount', 15, 2);
                $table->date('allocation_date');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('allocation_type');
            });
        }

        // ---- Expenses / disbursements ----
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('reference_number')->unique();
                $table->string('expense_category');
                $table->string('department')->nullable();
                $table->string('payee');
                $table->decimal('amount', 15, 2);
                $table->date('expense_date');
                $table->text('description')->nullable();
                $table->string('supporting_document')->nullable();
                $table->string('approval_status', 30)->default('pending'); // pending, approved, rejected
                $table->string('payment_status', 30)->default('pending');  // pending, paid
                $table->foreignId('budget_plan_id')->nullable()->constrained('budget_plans')->nullOnDelete();
                $table->foreignId('fund_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['approval_status', 'payment_status']);
                $table->index('expense_date');
            });
        }

        // ---- Accounts payable ----
        if (!Schema::hasTable('accounts_payable')) {
            Schema::create('accounts_payable', function (Blueprint $table) {
                $table->id();
                $table->string('vendor');
                $table->string('invoice_number')->unique();
                $table->date('invoice_date');
                $table->date('due_date');
                $table->decimal('amount', 15, 2);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->text('payment_schedule')->nullable();
                $table->string('payment_status', 30)->default('pending');
                $table->string('supporting_document')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['payment_status', 'due_date']);
            });
        }

        if (!Schema::hasTable('payable_payments')) {
            Schema::create('payable_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounts_payable_id')->constrained('accounts_payable')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->string('payment_method', 50)->nullable();
                $table->string('reference_number')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // ---- Funds ----
        if (!Schema::hasTable('funds')) {
            Schema::create('funds', function (Blueprint $table) {
                $table->id();
                $table->string('fund_name')->unique();
                $table->string('fund_source')->nullable();
                $table->string('fund_type', 50); // general, academic, scholarship, department, campus, emergency, special
                $table->decimal('initial_balance', 15, 2)->default(0);
                $table->decimal('current_balance', 15, 2)->default(0);
                $table->decimal('reserved_amount', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->string('status', 30)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fund_allocations')) {
            Schema::create('fund_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
                $table->foreignId('budget_plan_id')->nullable()->constrained('budget_plans')->nullOnDelete();
                $table->string('allocated_to')->nullable();
                $table->decimal('amount', 15, 2);
                $table->date('allocation_date');
                $table->string('status', 30)->default('allocated');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fund_transactions')) {
            Schema::create('fund_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
                $table->string('transaction_type', 30); // inflow, outflow, reservation, release
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->string('reference_number')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['fund_id', 'transaction_type']);
            });
        }

        // ---- Assets ----
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code')->unique();
                $table->string('asset_name');
                $table->string('asset_category');
                $table->string('serial_number')->nullable();
                $table->date('acquisition_date');
                $table->decimal('acquisition_cost', 15, 2);
                $table->decimal('salvage_value', 15, 2)->default(0);
                $table->integer('useful_life_years');
                $table->string('location')->nullable();
                $table->string('department')->nullable();
                $table->string('custodian')->nullable();
                $table->string('asset_status', 30)->default('active');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['asset_status', 'asset_category']);
            });
        }

        // ---- Column extensions on existing tables (guarded) ----
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'transaction_number')) {
                $table->string('transaction_number')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('payments', 'fee_category')) {
                $table->string('fee_category')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'verification_status')) {
                $table->string('verification_status', 30)->default('pending')->after('status');
            }
            if (!Schema::hasColumn('payments', 'proof_of_payment')) {
                $table->string('proof_of_payment')->nullable()->after('reference_number');
            }
            if (!Schema::hasColumn('payments', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_requests', 'request_number')) {
                $table->string('request_number')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('procurement_requests', 'requesting_department')) {
                $table->string('requesting_department')->nullable()->after('student_id');
            }
            if (!Schema::hasColumn('procurement_requests', 'item_description')) {
                $table->text('item_description')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('procurement_requests', 'quantity')) {
                $table->integer('quantity')->nullable()->after('item_description');
            }
            if (!Schema::hasColumn('procurement_requests', 'estimated_cost')) {
                $table->decimal('estimated_cost', 15, 2)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('procurement_requests', 'supplier')) {
                $table->string('supplier')->nullable()->after('estimated_cost');
            }
            if (!Schema::hasColumn('procurement_requests', 'justification')) {
                $table->text('justification')->nullable()->after('supplier');
            }
            if (!Schema::hasColumn('procurement_requests', 'supporting_document')) {
                $table->string('supporting_document')->nullable()->after('justification');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'description')) {
                $table->text('description')->nullable()->after('record_id');
            }
            if (!Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
        Schema::dropIfExists('fund_allocations');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('payable_payments');
        Schema::dropIfExists('accounts_payable');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budget_plans');
        Schema::dropIfExists('assets');
    }
};
