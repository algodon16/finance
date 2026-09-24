<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class FmsAiTest extends TestCase
{
    public function test_ai_recommendation_renders_labeled_schedule(): void
    {
        $admin = User::where('email', 'admin@school.edu')->first();
        $res = $this->actingAs($admin)->post('/admin/budgets/ai', [
            'declared_allowance' => 5000,
            'payment_frequency' => 'monthly',
            'outstanding_balance' => 20000,
            'payment_deadline' => today()->addMonths(4)->toDateString(),
        ]);
        $res->assertStatus(200);
        $res->assertSee('AI-Generated Recommendation', false);
        $res->assertSee('Suggested installment amount', false);
        $res->assertSee('P5,000.00', false);
    }
}
