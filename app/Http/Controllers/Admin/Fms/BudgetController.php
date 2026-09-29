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
        $q = BudgetPlan::with('items');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%")->orWhere('budget_category', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('fiscal_year')) $q->where('fiscal_year', $request->fiscal_year);
        $plans = $q->orderBy('created_at', 'desc')->paginate(12)->withQueryString();
        $years = BudgetPlan::select('fiscal_year')->distinct()->orderBy('fiscal_year')->pluck('fiscal_year');
        $counts = [
            'pending' => (int) BudgetPlan::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (int) BudgetPlan::whereIn('status', ['approved', 'active'])->count(),
            'rejected' => (int) BudgetPlan::whereIn('status', ['rejected', 'for_revision', 'revision'])->count(),
            'all' => (int) BudgetPlan::count(),
            'total_proposed' => (float) BudgetPlan::sum('allocated_amount'),
        ];
        return view('admin.fms.budgets.index', compact('plans', 'counts', 'years'));
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
        // Admin-created official budgets must not duplicate an existing official plan.
        if (in_array($data['status'], BudgetPlan::OFFICIAL, true) && ! empty($data['department'])) {
            if ($dup = BudgetPlan::officialDuplicateExists($data['department'], $data['fiscal_year'])) {
                return back()->withErrors(['department' => 'Department "'.$data['department'].'" already has an official budget for '.$data['fiscal_year'].' (BUD-'.$dup->id.'). Review/approve that record instead of creating a duplicate.'])->withInput();
            }
        }
        $data['utilized_amount'] = 0;
        $data['created_by'] = auth()->id();
        $plan = BudgetPlan::create($data);
        AuditService::log('create', 'budget_plans', (string) $plan->id, null, null, "Admin ".auth()->user()->name." created Budget Plan #{$plan->id} ({$plan->budget_name}).");
        return redirect()->route('admin.budgets.index')->with('success', 'Budget plan created.');
    }

    public function show(BudgetPlan $budget)
    {
        $budget->load(['items', 'allocations', 'creator', 'approver', 'submitter', 'reviewer']);
        $allocated = (float) $budget->allocations()->sum('amount');
        $history = \App\Models\AuditLog::with('user')->where('module', 'budget_plans')->where('record_id', (string) $budget->id)->latest('id')->take(30)->get();
        return view('admin.fms.budgets.show', compact('budget', 'allocated', 'history'));
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
        // The approved amount is official — it cannot be rewritten through edit once official.
        if ($budget->isOfficial() && bccomp((string) $data['allocated_amount'], (string) $budget->allocated_amount, 2) !== 0) {
            abort(422, 'The approved amount of an official budget cannot be changed directly. Adjust through allocations or return the plan for revision.');
        }
        // Flipping a plan to official must not create a duplicate either.
        $resultStatus = $data['status'];
        if (in_array($resultStatus, BudgetPlan::OFFICIAL, true) && ! empty($data['department'])) {
            if ($dup = BudgetPlan::officialDuplicateExists($data['department'], $data['fiscal_year'], $budget->id)) {
                return back()->withErrors(['department' => 'Department "'.$data['department'].'" already has an official budget for '.$data['fiscal_year'].' (BUD-'.$dup->id.').'])->withInput();
            }
        }
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

    /** Approve a submitted budget plan — same record, submitted → approved. */
    public function approve(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        // Approving must not crown a second official budget for the same department + year.
        if ($dup = BudgetPlan::officialDuplicateExists($budget->department, $budget->fiscal_year, $budget->id)) {
            abort(422, 'Department "'.$budget->department.'" already has an official budget for '.$budget->fiscal_year.' (BUD-'.$dup->id.'). Resolve the duplicate first.');
        }
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => auth()->id(), 'approved_at' => now(),
            'admin_remarks' => $request->input('admin_remarks') ?: $budget->admin_remarks,
        ]));
        AuditService::log('approve', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Budget Plan BUD-{$budget->id} ({$budget->budget_name}) — now in Budget Planning Overview as approved.");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} has been approved.", "Budget Plan '{$budget->budget_name}' was approved and is now available in the overview.");
        return back()->with('success', 'Budget plan approved. It is now visible as approved in both Admin and Accountant modules.');
    }

    /** Reject a submitted budget plan — same record, submitted → rejected (reason required). */
    public function reject(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be rejected.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $budget->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('reject', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Budget Plan BUD-{$budget->id}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit from Budget Planning.");
        return back()->with('success', 'Budget plan rejected and returned to Accountant for revision.');
    }

    /** Return for revision — same record, submitted → for_revision (admin comment required). */
    public function forRevision(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can return plans.');
        $data = $request->validate(['admin_remarks' => 'required|string|max:5000']);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be returned for revision.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'for_revision',
            'admin_remarks' => $data['admin_remarks'],
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('revise', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'for_revision'], "Admin ".auth()->user()->name." returned Budget Plan BUD-{$budget->id} for revision: {$data['admin_remarks']}");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} was returned for revision.", "Admin comment: {$data['admin_remarks']}");
        return back()->with('success', 'Budget plan returned to Accountant for revision.');
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
