<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FmsPagesTest extends TestCase
{
    public function test_all_admin_module_pages_render(): void
    {
        $admin = User::where('email', 'admin@school.edu')->first();
        $this->actingAs($admin);
        $urls = [
            '/admin/dashboard', '/admin/budgets', '/admin/budgets/create', '/admin/budgets/ai',
            '/admin/revenues', '/admin/revenues/create', '/admin/expenses', '/admin/expenses/create',
            '/admin/payables', '/admin/payables/create', '/admin/receivables', '/admin/receivables/assess',
            '/admin/funds', '/admin/funds/create', '/admin/procurement', '/admin/procurement/create',
            '/admin/assets', '/admin/assets/create', '/admin/reports', '/admin/reports/revenue',
            '/admin/reports/expenses', '/admin/reports/receivables', '/admin/reports/payables',
            '/admin/reports/budgets', '/admin/reports/assets', '/admin/reports/executive',
            '/admin/audit-trail', '/admin/settings',
        ];
        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertTrue(in_array($res->getStatusCode(), [200]), "Failed: {$url} returned {$res->getStatusCode()}");
        }
        // Dashboard shows real seeded figures, not the empty-state message.
        $this->get('/admin/dashboard')->assertSee('General Fund', false);
    }

    public function test_admin_procurement_store_without_student(): void
    {
        $admin = User::where('email', 'admin@school.edu')->first();
        $this->actingAs($admin);
        $res = $this->post('/admin/procurement', [
            'requesting_department' => 'Test Dept', 'item_description' => 'NSTP Uniform - 10 pcs',
            'quantity' => 10, 'estimated_cost' => 5000, 'justification' => 'Test justification.',
            'status' => 'draft',
        ]);
        $res->assertRedirect(route('admin.procurement.index'));
        $rec = \App\Models\ProcurementRequest::where('item_description', 'NSTP Uniform - 10 pcs')->first();
        $this->assertNotNull($rec);
        $rec->delete();
    }
}
