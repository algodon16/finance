<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\BudgetPlan;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class FundAllocationController extends Controller
{
    /**
     * Fund Management & Allocation is a MONITORING page.
     * Allocations arrive automatically from approved Budget Requests —
     * there is no manual allocation workflow here (single source of truth).
     */
    public function index(Request $request)
    {
        $funds = Fund::orderBy('fund_name')->get();
        $a = BudgetAllocation::with('budgetPlan')->orderByDesc('created_at');
        if ($request->filled('fund')) {
            $a->whereHas('budgetPlan', fn($w) => $w->where('funding_source', $request->fund));
        }
        if ($request->filled('department')) {
            $a->whereHas('budgetPlan', fn($w) => $w->where('department', $request->department));
        }
        $allocations = $a->paginate(12)->withQueryString();
        $departments = BudgetPlan::whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department');
        return view('accountant.fund-allocations.index', compact('funds', 'allocations', 'departments'));
    }

    public function create()
    {
        // No manual allocation workflow: allocations are auto-created on admin approval.
        return redirect()->route('accountant.fund-allocations.index')
            ->with('error', 'Allocations are created automatically when Admin approves a Budget Request. Start from Budget Requests instead.');
    }

    protected function manualBlocked(): never
    {
        abort(422, 'Manual allocations are disabled. Allocations are created automatically when Admin approves a Budget Request under Budget Planning & Allocation.');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $this->manualBlocked();
    }

    public function show(FundAllocation $fundAllocation)
    {
        $fundAllocation->load(['fund', 'budgetPlan']);
        $util = null;
        if ($fundAllocation->budget_plan_id && $fundAllocation->budgetPlan) {
            $b = $fundAllocation->budgetPlan;
            $already = (float) \App\Models\FundAllocation::where('budget_plan_id', $b->id)->where('status', 'approved')->where('id', '!=', $fundAllocation->id)->sum('amount');
            $util = ['approved' => (float) $b->allocated_amount, 'allocated' => $already, 'remaining' => (float) $b->remaining_amount, 'proposed' => (float) $fundAllocation->amount, 'after' => (float) $b->remaining_amount - (float) $fundAllocation->amount];
        }
        $history = AuditLog::where('module', 'fund_allocations')->where('record_id', (string) $fundAllocation->id)->latest('id')->take(20)->get();
        return view('accountant.fund-allocations.show', ['record' => $fundAllocation, 'util' => $util, 'history' => $history]);
    }

    public function edit(FundAllocation $fundAllocation)
    {
        // Read-only history: existing records stay viewable, never re-entered.
        return redirect()->route('accountant.fund-allocations.show', $fundAllocation);
    }

    public function update(Request $request, FundAllocation $fundAllocation)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $this->manualBlocked();
    }

    public function submit(FundAllocation $fundAllocation)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $this->manualBlocked();
    }

    /**
     * Allocations may only draw on an approved/active budget with room left.
     * Returns an error message, or null when the budget linkage is valid.
     */
    protected function budgetGuard(int $budgetId, float $amount): ?string
    {
        $budget = BudgetPlan::find($budgetId);
        if (! $budget) return 'Selected budget no longer exists.';
        if (! in_array($budget->status, ['approved', 'active'], true)) {
            return 'Allocation must link an approved budget. Budget #'.$budget->id.' is currently '.$budget->status.'.';
        }
        $remaining = (float) $budget->remaining_amount;
        if ($amount > $remaining) {
            return 'Allocation exceeds remaining budget of P'.number_format($remaining, 2).' (Budget #'.$budget->id.').';
        }
        return null;
    }

    public function cancel(FundAllocation $fundAllocation)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        WorkflowService::cancelSubmission($fundAllocation, 'fund_allocations', 'Fund Allocation');
        return back()->with('success', 'Submission withdrawn.');
    }
}
