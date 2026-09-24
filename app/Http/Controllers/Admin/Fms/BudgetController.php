<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\BudgetAllocation;
use App\Models\BudgetPlan;
use App\Models\Payment;
use App\Models\StudentAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $q = BudgetPlan::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('fiscal_year')) $q->where('fiscal_year', $request->fiscal_year);
        $plans = $q->orderBy('created_at', 'desc')->paginate(12)->withQueryString();
        return view('admin.fms.budgets.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.fms.budgets.form', ['plan' => new BudgetPlan()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'department' => 'nullable|string|max:255',
            'budget_category' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,closed,draft',
            'description' => 'nullable|string|max:5000',
        ]);
        $data['utilized_amount'] = 0;
        $data['created_by'] = auth()->id();
        $plan = BudgetPlan::create($data);
        AuditService::log('create', 'budget_plans', (string) $plan->id, null, null, "Admin ".auth()->user()->name." created Budget Plan #{$plan->id} ({$plan->budget_name}).");
        return redirect()->route('admin.budgets.index')->with('success', 'Budget plan created.');
    }

    public function show(BudgetPlan $budget)
    {
        $budget->load('allocations');
        $allocated = (float) $budget->allocations()->sum('amount');
        return view('admin.fms.budgets.show', compact('budget', 'allocated'));
    }

    public function edit(BudgetPlan $budget)
    {
        return view('admin.fms.budgets.form', ['plan' => $budget]);
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'department' => 'nullable|string|max:255',
            'budget_category' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,closed,draft',
            'description' => 'nullable|string|max:5000',
        ]);
        $old = $budget->toArray();
        $budget->update($data);
        AuditService::log('update', 'budget_plans', (string) $budget->id, $old, $budget->fresh()->toArray(), "Admin ".auth()->user()->name." updated Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Budget plan updated.');
    }

    public function destroy(BudgetPlan $budget)
    {
        $old = $budget->toArray();
        $budget->delete();
        AuditService::log('delete', 'budget_plans', (string) $budget->id, $old, null, "Admin ".auth()->user()->name." deleted Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.index')->with('success', 'Budget plan deleted.');
    }

    public function allocate(Request $request, BudgetPlan $budget)
    {
        $data = $request->validate([
            'allocation_type' => 'required|in:department,program,scholarship,operational,academic',
            'allocated_to' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
        ]);
        // Server-side guard: allocation cannot exceed remaining.
        $remaining = (float) $budget->remaining_amount;
        if ((float) $data['amount'] > $remaining) {
            return back()->withErrors(['amount' => 'Allocation exceeds remaining budget of P'.number_format($remaining, 2).'.'])->withInput();
        }
        DB::transaction(function () use ($data, $budget) {
            BudgetAllocation::create($data + ['budget_plan_id' => $budget->id, 'created_by' => auth()->id()]);
            $budget->increment('utilized_amount', $data['amount']);
        });
        AuditService::log('allocate', 'budget_allocations', (string) $budget->id, null, null, "Admin ".auth()->user()->name." allocated P".number_format($data['amount'], 2)." from Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Funds allocated.');
    }

    public function destroyAllocation(BudgetPlan $budget, BudgetAllocation $allocation)
    {
        DB::transaction(function () use ($budget, $allocation) {
            $budget->decrement('utilized_amount', $allocation->amount);
            $allocation->delete();
        });
        AuditService::log('delete', 'budget_allocations', (string) $allocation->id, null, null, "Admin ".auth()->user()->name." removed allocation #{$allocation->id} from Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Allocation removed.');
    }

    /**
     * AI-assisted budget planning form (GET — no validation, display only).
     */
    public function ai()
    {
        return view('admin.fms.budgets.ai');
    }

    /**
     * AI-assisted budget planning (rule-based forecasting engine).
     * Recommendations are labeled and never auto-applied.
     */
    public function aiRecommend(Request $request)
    {
        $data = $request->validate([
            'declared_allowance' => 'required|numeric|min:0|max:999999999.99',
            'payment_frequency' => 'required|in:monthly,quarterly,semestral,annual,one-time',
            'outstanding_balance' => 'required|numeric|min:0|max:999999999.99',
            'total_assessment' => 'nullable|numeric|min:0|max:999999999.99',
            'payment_deadline' => 'required|date|after:today',
            'available_budget' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $balance = (float) $data['outstanding_balance'];
        $deadline = new \DateTime($data['payment_deadline']);
        $now = new \DateTime('today');
        $monthsLeft = max(1, ($deadline->format('Y') - $now->format('Y')) * 12 + ($deadline->format('n') - $now->format('n')));

        $divisor = match ($data['payment_frequency']) {
            'monthly' => $monthsLeft,
            'quarterly' => max(1, (int) ceil($monthsLeft / 3)),
            'semestral' => max(1, (int) ceil($monthsLeft / 6)),
            'annual' => max(1, (int) ceil($monthsLeft / 12)),
            default => 1,
        };

        $suggestedInstallment = round($balance / $divisor, 2);
        $allowance = (float) $data['declared_allowance'];
        $affordable = $allowance > 0 ? $suggestedInstallment <= $allowance : true;

        // Build schedule.
        $schedule = [];
        $running = $balance;
        $cursor = clone $now;
        for ($i = 1; $i <= $divisor && $running > 0.009; $i++) {
            $cursor->modify('+1 month');
            $pay = min($suggestedInstallment, $running);
            $running = round($running - $pay, 2);
            $schedule[] = ['installment' => $i, 'due' => $cursor->format('Y-m-d'), 'amount' => $pay, 'projected_remaining' => max(0, $running)];
        }

        $recommendation = [
            'suggested_installment' => $suggestedInstallment,
            'num_installments' => $divisor,
            'months_to_deadline' => $monthsLeft,
            'projected_remaining_after_plan' => end($schedule)['projected_remaining'] ?? $balance,
            'completion_projection' => $cursor->format('Y-m-d'),
            'affordable' => $affordable,
            'allocation_forecast' => $affordable
                ? 'Declared allowance covers the suggested installment schedule.'
                : 'Declared allowance is below the suggested installment. Consider extending the schedule or reducing the balance with a partial payment.',
            'schedule' => $schedule,
        ];

        // Cross-reference real institutional aggregates (read-only context).
        $context = [
            'avg_student_balance' => round((float) (StudentAccount::where('outstanding_balance', '>', 0)->avg('outstanding_balance') ?? 0), 2),
            'recent_avg_payment' => round((float) (Payment::where('status', 'approved')->avg('amount') ?? 0), 2),
        ];

        return view('admin.fms.budgets.ai', compact('recommendation', 'context', 'data'));
    }
}
