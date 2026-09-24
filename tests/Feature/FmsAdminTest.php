<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FmsAdminTest extends TestCase
{
    public function test_login_page_matches_spec(): void
    {
        $res = $this->get('/login');
        $res->assertStatus(200);
        $res->assertSee('BESTLINK COLLEGE OF THE PHILIPPINES', false);
        $res->assertSee('Email Address', false);
        $res->assertSee('Password', false);
        $this->assertStringNotContainsString('Remember Me', $res->getContent());
        $this->assertStringNotContainsString('Remember me', $res->getContent());
        $this->assertStringNotContainsString('Student Portal', $res->getContent());
    }

    public function test_invalid_login_rejected(): void
    {
        $res = $this->post('/login', ['email' => 'admin@school.edu', 'password' => 'wrong-password-123']);
        $res->assertStatus(302);
        $this->assertGuest();
    }

    public function test_admin_login_and_dashboard(): void
    {
        $res = $this->post('/login', ['email' => 'admin@school.edu', 'password' => 'password']);
        $res->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'admin@school.edu')->first());
        $dash = $this->get('/admin/dashboard');
        $dash->assertStatus(200);
        $dash->assertSee('Total Revenue', false);
        $dash->assertSee('Accounts Receivable', false);
    }

    public function test_guest_blocked_from_admin_modules(): void
    {
        foreach (['/admin/budgets', '/admin/revenues', '/admin/expenses', '/admin/payables', '/admin/receivables', '/admin/funds', '/admin/procurement', '/admin/assets', '/admin/reports', '/admin/audit-trail', '/admin/settings'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_non_admin_gets_403_on_fms(): void
    {
        $student = User::where('role', 'student')->first();
        $this->actingAs($student)->get('/admin/budgets')->assertStatus(403);
    }

    public function test_budget_crud_and_allocation_math(): void
    {
        $admin = User::where('email', 'admin@school.edu')->first();
        $this->actingAs($admin);
        $uniq = uniqid();
        $planName = 'FMS Test Plan '.$uniq;

        $create = $this->post('/admin/budgets', [
            'budget_name' => $planName, 'fiscal_year' => '2099-2100',
            'budget_category' => 'Academic', 'allocated_amount' => 100000,
            'start_date' => '2099-06-01', 'end_date' => '2100-05-31', 'status' => 'active',
        ]);
        $create->assertRedirect(route('admin.budgets.index'));
        $plan = \App\Models\BudgetPlan::where('budget_name', $planName)->first();
        $this->assertNotNull($plan);

        // Over-allocation must be rejected server-side.
        $this->post("/admin/budgets/{$plan->id}/allocate", [
            'allocation_type' => 'department', 'amount' => 99999999,
            'allocation_date' => today()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->post("/admin/budgets/{$plan->id}/allocate", [
            'allocation_type' => 'department', 'allocated_to' => 'Test Dept',
            'amount' => 25000, 'allocation_date' => today()->toDateString(),
        ])->assertRedirect();
        $this->assertEquals('75000.00', $plan->fresh()->remaining_amount);

        // Negative expense rejected.
        $this->post('/admin/expenses', [
            'expense_category' => 'Supplies', 'payee' => 'Test', 'amount' => -50,
            'expense_date' => today()->toDateString(), 'reference_number' => 'EXP-TEST-NEG',
        ])->assertSessionHasErrors('amount');

        // Asset depreciation math: (120000-20000)/5 = 20000/yr.
        $asset = \App\Models\Asset::create([
            'asset_code' => 'AST-TEST-'.$uniq, 'asset_name' => 'FMS Test Asset',
            'asset_category' => 'Office Equipment', 'acquisition_date' => today()->subYears(2),
            'acquisition_cost' => 120000, 'salvage_value' => 20000,
            'useful_life_years' => 5, 'asset_status' => 'active', 'created_by' => $admin->id,
        ]);
        $this->assertEquals('20000.00', $asset->annual_depreciation);
        $this->assertEquals('1666.67', $asset->monthly_depreciation);

        // Audit log written for budget create.
        $this->assertTrue(\App\Models\AuditLog::where('module', 'budget_plans')->where('record_id', (string) $plan->id)->exists());

        // Cleanup (test data only).
        $asset->delete();
        $plan->allocations()->delete();
        $plan->delete();
    }

    public function test_404_handled(): void
    {
        $admin = User::where('email', 'admin@school.edu')->first();
        $this->actingAs($admin)->get('/admin/this-route-does-not-exist-xyz')->assertStatus(404);
    }
}
