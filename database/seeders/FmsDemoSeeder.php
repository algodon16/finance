<?php

namespace Database\Seeders;

use App\Models\AccountsPayable;
use App\Models\Asset;
use App\Models\BudgetAllocation;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\ProcurementRequest;
use Illuminate\Database\Seeder;

/**
 * Demo data for the BCP Financial Management System admin modules.
 * Idempotent: only seeds when the respective table is empty.
 * Never touches existing tables/records outside the new FMS modules.
 */
class FmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = \App\Models\User::where('role', 'admin')->value('id');

        if (BudgetPlan::count() === 0) {
            $plan = BudgetPlan::create([
                'budget_name' => 'Academic Operations 2026-2027', 'academic_year' => '2026-2027',
                'department' => 'Academic Affairs', 'budget_category' => 'Academic',
                'allocated_amount' => 5000000, 'utilized_amount' => 0,
                'start_date' => '2026-06-01', 'end_date' => '2027-05-31',
                'status' => 'active', 'description' => 'Institutional academic operations budget.',
                'created_by' => $adminId,
            ]);
            foreach ([
                ['allocation_type' => 'department', 'allocated_to' => 'College of Computer Studies', 'amount' => 1200000],
                ['allocation_type' => 'scholarship', 'allocated_to' => 'Academic Scholarship Grants', 'amount' => 800000],
                ['allocation_type' => 'operational', 'allocated_to' => 'Campus Utilities', 'amount' => 450000],
            ] as $a) {
                BudgetAllocation::create($a + ['budget_plan_id' => $plan->id, 'allocation_date' => today(), 'created_by' => $adminId]);
                $plan->increment('utilized_amount', $a['amount']);
            }
        }

        if (Fund::count() === 0) {
            foreach ([
                ['fund_name' => 'General Fund', 'fund_type' => 'general', 'initial_balance' => 10000000],
                ['fund_name' => 'Scholarship Fund', 'fund_type' => 'scholarship', 'initial_balance' => 2500000],
                ['fund_name' => 'Emergency Reserve', 'fund_type' => 'emergency', 'initial_balance' => 1000000],
            ] as $f) {
                $fund = Fund::create($f + ['fund_source' => 'Institutional collections', 'current_balance' => $f['initial_balance'], 'reserved_amount' => 0, 'status' => 'active', 'created_by' => $adminId]);
                FundTransaction::create(['fund_id' => $fund->id, 'transaction_type' => 'inflow', 'amount' => $f['initial_balance'], 'transaction_date' => today(), 'reference_number' => 'OPENING-'.$fund->id, 'description' => 'Opening balance', 'created_by' => $adminId]);
            }
        }

        if (Expense::count() === 0) {
            Expense::create(['reference_number' => 'EXP-2026-0001', 'expense_category' => 'Utilities', 'department' => 'Administration', 'payee' => 'Meralco', 'amount' => 85000, 'expense_date' => today()->subDays(10), 'description' => 'Campus electricity bill.', 'approval_status' => 'approved', 'payment_status' => 'paid', 'created_by' => $adminId, 'approved_by' => $adminId]);
            Expense::create(['reference_number' => 'EXP-2026-0002', 'expense_category' => 'Supplies', 'department' => 'Academic Affairs', 'payee' => 'Office Supplies Inc.', 'amount' => 42000, 'expense_date' => today()->subDays(3), 'description' => 'Instructional materials printing.', 'approval_status' => 'pending', 'payment_status' => 'pending', 'created_by' => $adminId]);
        }

        if (AccountsPayable::count() === 0) {
            AccountsPayable::create(['vendor' => 'Campus Builders Corp.', 'invoice_number' => 'INV-2026-0001', 'invoice_date' => today()->subDays(45), 'due_date' => today()->subDays(5), 'amount' => 350000, 'amount_paid' => 100000, 'payment_status' => 'partially_paid', 'payment_schedule' => 'Two tranches.', 'created_by' => $adminId]);
            AccountsPayable::create(['vendor' => 'Library Books Supplier', 'invoice_number' => 'INV-2026-0002', 'invoice_date' => today()->subDays(5), 'due_date' => today()->addDays(25), 'amount' => 120000, 'amount_paid' => 0, 'payment_status' => 'pending', 'created_by' => $adminId]);
        }

        if (Asset::count() === 0) {
            Asset::create(['asset_code' => 'AST-2026-0001', 'asset_name' => 'Computer Laboratory Units (Batch A)', 'asset_category' => 'Computer Equipment', 'serial_number' => 'BCP-COMP-2026-A', 'acquisition_date' => today()->subYears(2), 'acquisition_cost' => 1500000, 'salvage_value' => 150000, 'useful_life_years' => 5, 'location' => 'CCS Building, Room 301', 'department' => 'College of Computer Studies', 'custodian' => 'Lab Custodian', 'asset_status' => 'active', 'created_by' => $adminId]);
            Asset::create(['asset_code' => 'AST-2026-0002', 'asset_name' => 'Library Bookshelves', 'asset_category' => 'Furniture', 'acquisition_date' => today()->subYears(1), 'acquisition_cost' => 240000, 'salvage_value' => 24000, 'useful_life_years' => 10, 'location' => 'Main Library', 'department' => 'Library', 'asset_status' => 'active', 'created_by' => $adminId]);
        }

        if (!ProcurementRequest::whereNotNull('request_number')->exists()) {
            $studentId = \App\Models\Student::value('id');
            ProcurementRequest::create(['student_id' => $studentId, 'request_number' => 'PR-'.now()->format('Ymd').'-DEMO01', 'requesting_department' => 'Student Affairs', 'item_description' => 'Gala Uniform - 200 sets', 'quantity' => 200, 'estimated_cost' => 180000, 'total_amount' => 180000, 'supplier' => 'BCP Uniform Supplier', 'justification' => 'Incoming first-year student uniform requirement.', 'status' => 'submitted']);
        }
    }
}
