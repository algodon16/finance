<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\AuditService;
use App\Services\BudgetPlannerService;
use Illuminate\Http\Request;

class BudgetPlannerController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        $budget = Budget::where('student_id', $student->id)->first();

        // Result flashed by calculate() when JavaScript is unavailable.
        $budgetPlan = session('budgetPlan');

        return view('student.budget-planner.index', compact('student', 'budget', 'budgetPlan'));
    }

    public function calculate(Request $request)
    {
        $student = auth()->user()->student;

        $validated = $request->validate([
            'allowance_frequency' => 'required|in:weekly,monthly',
            'allowance_amount' => 'required|numeric|min:0.01',
        ], [
            'allowance_frequency.required' => 'Please select your allowance frequency.',
            'allowance_frequency.in' => 'Please select your allowance frequency.',
            'allowance_amount.required' => 'Please enter your budget amount.',
            'allowance_amount.numeric' => 'Please enter a valid budget amount.',
            'allowance_amount.min' => 'Budget amount must be greater than zero.',
        ]);

        $amount = round((float) $validated['allowance_amount'], 2);
        $frequency = $validated['allowance_frequency'];

        // Personal planning record only. Official balances, payments, ledger and
        // receivable records are never touched by this planner.
        $studentAccount = $student->studentAccount;
        Budget::updateOrCreate(
            ['student_id' => $student->id],
            [
                'allowance_frequency' => $frequency,
                'allowance_amount' => $amount,
                'target_balance' => $studentAccount ? $studentAccount->outstanding_balance : 0,
                'target_date' => now()->addWeeks(8),
            ]
        );

        // Pure percentage-based allocation (works offline, no AI service needed).
        $allocation = BudgetPlannerService::generateAllocation($amount);

        $budgetPlan = [
            'allowance_amount' => $amount,
            'allowance_frequency' => $frequency,
            'allocation' => $allocation['items'],
            'rates' => $allocation['rates'],
            'total' => $allocation['total'],
        ];

        AuditService::log('calculate', 'budget_planner', $student->id, null, $budgetPlan);

        // The planner form submits via fetch: return JSON for dynamic display.
        if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'budgetPlan' => $budgetPlan]);
        }

        return redirect()->route('student.budget.index')->with('budgetPlan', $budgetPlan);
    }
}
