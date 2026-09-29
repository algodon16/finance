<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Models\FundTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundController extends Controller
{
    public function index(Request $request)
    {
        $q = Fund::query();
        if ($request->filled('search')) $q->where('fund_name', 'ilike', '%'.$request->search.'%');
        if ($request->filled('fund_type')) $q->where('fund_type', $request->fund_type);
        $funds = $q->orderBy('fund_name')->paginate(12)->withQueryString();
        $totals = [
            'initial' => (float) Fund::sum('initial_balance'),
            'current' => (float) Fund::sum('current_balance'),
            'reserved' => (float) Fund::sum('reserved_amount'),
        ];
        $splitFunds = Fund::where('status', 'active')->orderBy('fund_name')->get();
        return view('admin.fms.funds.index', compact('funds', 'totals', 'splitFunds'));
    }

    public function create()
    {
        return view('admin.fms.funds.form', ['fund' => new Fund()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fund_name' => 'required|string|max:255|unique:funds,fund_name',
            'fund_source' => 'nullable|string|max:255',
            'fund_type' => 'required|in:general,academic,scholarship,department,campus,emergency,special',
            'initial_balance' => 'required|numeric|min:0|max:999999999999.99',
            'description' => 'nullable|string|max:5000',
            'status' => 'required|in:active,inactive',
        ]);
        $data['current_balance'] = $data['initial_balance'];
        $data['reserved_amount'] = 0;
        $data['created_by'] = auth()->id();
        $fund = DB::transaction(function () use ($data) {
            $f = Fund::create($data);
            FundTransaction::create([
                'fund_id' => $f->id, 'transaction_type' => 'inflow',
                'amount' => $f->initial_balance, 'transaction_date' => today(),
                'reference_number' => 'OPENING-'.$f->id,
                'description' => 'Opening balance', 'created_by' => auth()->id(),
            ]);
            return $f;
        });
        AuditService::log('create', 'funds', (string) $fund->id, null, null, "Admin ".auth()->user()->name." created Fund #{$fund->fund_name}.");
        return redirect()->route('admin.funds.index')->with('success', 'Fund created.');
    }

    public function show(Fund $fund)
    {
        $fund->load(['allocations.budgetPlan', 'transactions']);
        $pendingAllocations = FundAllocation::with(['budgetPlan'])->where('fund_id', $fund->id)->whereIn('status', ['submitted', 'under_review'])->latest('submitted_at')->get();
        $budgets = BudgetPlan::whereIn('status', ['approved', 'active'])->orderBy('budget_name')->get();
        return view('admin.fms.funds.show', compact('fund', 'pendingAllocations', 'budgets'));
    }

    /**
     * Budget ID quick lookup for Fund Management.
     * GET /admin/funds/budget-preview?q=25 — approved budgets only.
     */
    public function budgetPreview(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        abort_if($q === '', 404, 'No budget specified.');
        $budget = ctype_digit($q)
            ? BudgetPlan::find((int) $q)
            : BudgetPlan::where('budget_name', 'ilike', "%{$q}%")->first();
        abort_if(! $budget || ! in_array($budget->status, ['approved', 'active'], true), 404, 'No approved budget found for "'.$q.'".');
        return response()->json([
            'id' => $budget->id,
            'label' => '#'.$budget->id.' — '.$budget->budget_name,
            'department' => $budget->department,
            'fiscal_year' => $budget->fiscal_year,
            'status' => $budget->status,
            'allocated' => (float) $budget->allocated_amount,
            'utilized' => (float) $budget->utilized_amount,
            'remaining' => (float) $budget->remaining_amount,
        ]);
    }

    public function edit(Fund $fund)
    {
        return view('admin.fms.funds.form', compact('fund'));
    }

    public function update(Request $request, Fund $fund)
    {
        $data = $request->validate([
            'fund_name' => 'required|string|max:255|unique:funds,fund_name,'.$fund->id,
            'fund_source' => 'nullable|string|max:255',
            'fund_type' => 'required|in:general,academic,scholarship,department,campus,emergency,special',
            'description' => 'nullable|string|max:5000',
            'status' => 'required|in:active,inactive',
        ]);
        $old = $fund->toArray();
        $fund->update($data);
        AuditService::log('update', 'funds', (string) $fund->id, $old, $fund->fresh()->toArray(), "Admin ".auth()->user()->name." updated Fund #{$fund->fund_name}.");
        return redirect()->route('admin.funds.show', $fund)->with('success', 'Fund updated.');
    }

    public function transaction(Request $request, Fund $fund)
    {
        $data = $request->validate([
            'transaction_type' => 'required|in:inflow,outflow,reservation,release',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'transaction_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($fund, $data) {
            $fund->lockForUpdate()->first();
            switch ($data['transaction_type']) {
                case 'inflow':
                    $fund->increment('current_balance', $data['amount']);
                    break;
                case 'outflow':
                    abort_if((float) $fund->current_balance - (float) $fund->reserved_amount < (float) $data['amount'], 422, 'Insufficient available balance.');
                    $fund->decrement('current_balance', $data['amount']);
                    break;
                case 'reservation':
                    abort_if((float) $fund->current_balance - (float) $fund->reserved_amount < (float) $data['amount'], 422, 'Insufficient available balance.');
                    $fund->increment('reserved_amount', $data['amount']);
                    break;
                case 'release':
                    abort_if((float) $fund->reserved_amount < (float) $data['amount'], 422, 'Release exceeds reserved amount.');
                    $fund->decrement('reserved_amount', $data['amount']);
                    break;
            }
            FundTransaction::create($data + ['fund_id' => $fund->id, 'created_by' => auth()->id()]);
        });
        AuditService::log('fund_transaction', 'funds', (string) $fund->id, null, null, "Admin ".auth()->user()->name." posted {$data['transaction_type']} of P".number_format($data['amount'], 2)." to Fund #{$fund->fund_name}.");
        return back()->with('success', 'Fund transaction posted.');
    }

    /**
     * Record allocation(s) from a fund. Accepts either the legacy single
     * allocation (allocated_to + amount) or a multi-row budget split
     * (splits[][allocated_to, amount]) — all rows created atomically.
     */
    public function allocate(Request $request, Fund $fund)
    {
        if ($request->filled('splits')) {
            return $this->allocateSplit($request, $fund);
        }
        $data = $request->validate([
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'allocated_to' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:2000',
        ]);
        abort_if((float) $fund->available_amount < (float) $data['amount'], 422, 'Allocation exceeds available fund balance.');
        // Direct admin allocations also draw only on approved budgets.
        if (! empty($data['budget_plan_id'])) {
            $budget = BudgetPlan::findOrFail($data['budget_plan_id']);
            abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Allocation must link an approved budget. Budget #'.$budget->id.' is '.$budget->status.'.');
            abort_if((float) $data['amount'] > (float) $budget->remaining_amount, 422, 'Allocation exceeds remaining budget of P'.number_format($budget->remaining_amount, 2).'.');
        }
        DB::transaction(function () use ($fund, $data) {
            FundAllocation::create($data + ['fund_id' => $fund->id, 'created_by' => auth()->id()]);
            $fund->decrement('current_balance', $data['amount']);
        });
        AuditService::log('allocate', 'fund_allocations', (string) $fund->id, null, null, "Admin ".auth()->user()->name." allocated P".number_format($data['amount'], 2)." from Fund #{$fund->fund_name}.");
        return back()->with('success', 'Fund allocation recorded.');
    }

    /**
     * Auto-generate many allocation rows in one submit (budget split).
     * The whole split fails together when the total exceeds the fund
     * or the linked budget — never a half-written split.
     */
    protected function allocateSplit(Request $request, Fund $fund)
    {
        $data = $request->validate([
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'allocation_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:2000',
            'splits' => 'required|array|min:1|max:50',
            'splits.*.allocated_to' => 'required|string|max:255',
            'splits.*.amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        $total = round(collect($data['splits'])->sum('amount'), 2);
        abort_if($total <= 0, 422, 'Split total must be greater than 0.');
        abort_if($total > (float) $fund->available_amount, 422, 'Split total P'.number_format($total, 2).' exceeds available fund of P'.number_format($fund->available_amount, 2).'.');
        $budget = null;
        if (! empty($data['budget_plan_id'])) {
            $budget = BudgetPlan::lockForUpdate()->findOrFail($data['budget_plan_id']);
            abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Split must link an approved budget. Budget #'.$budget->id.' is '.$budget->status.'.');
            abort_if($total > (float) $budget->remaining_amount, 422, 'Split total exceeds remaining budget of P'.number_format($budget->remaining_amount, 2).'.');
        }
        $count = 0;
        DB::transaction(function () use ($fund, $data, $total, &$count) {
            foreach ($data['splits'] as $row) {
                FundAllocation::create([
                    'fund_id' => $fund->id,
                    'budget_plan_id' => $data['budget_plan_id'] ?? null,
                    'allocated_to' => $row['allocated_to'],
                    'amount' => $row['amount'],
                    'allocation_date' => $data['allocation_date'],
                    'remarks' => $data['remarks'] ?? null,
                    'status' => 'allocated',
                    'created_by' => auth()->id(),
                ]);
                $count++;
            }
            $fund->decrement('current_balance', $total);
        });
        AuditService::log('allocate', 'fund_allocations', (string) $fund->id, null, null, "Admin ".auth()->user()->name." auto-split P".number_format($total, 2)." from Fund #{$fund->fund_name} into {$count} allocation(s)".($budget ? " under Budget #{$budget->id}" : '').".");
        return back()->with('success', "Budget split recorded: {$count} allocation(s), total P".number_format($total, 2).'.');
    }

    /**
     * Multi-fund budget split: one budget covered by many fund sources.
     * Each row carries its own fund + amount; the whole split is atomic —
     * per-fund availability and budget remaining are enforced together.
     * POST /admin/funds/allocate-multi
     */
    public function allocateMulti(Request $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can allocate.');
        $data = $request->validate([
            'budget_plan_id' => 'required|exists:budget_plans,id',
            'allocated_to' => 'required|string|max:255',
            'allocation_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:2000',
            'rows' => 'required|array|min:1|max:50',
            'rows.*.fund_id' => 'required|exists:funds,id',
            'rows.*.amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        $rows = collect($data['rows'])->filter(fn($r) => (float) $r['amount'] > 0)->values();
        abort_if($rows->isEmpty(), 422, 'Enter at least one amount greater than 0.');
        $total = round($rows->sum('amount'), 2);

        $budget = BudgetPlan::lockForUpdate()->findOrFail($data['budget_plan_id']);
        abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Split must link an approved budget. Budget #'.$budget->id.' is '.$budget->status.'.');
        abort_if($total > (float) $budget->remaining_amount, 422, 'Split total P'.number_format($total, 2).' exceeds budget remaining of P'.number_format($budget->remaining_amount, 2).'.');

        // Per-fund availability (rows sharing one fund add up).
        $perFund = $rows->groupBy('fund_id')->map(fn($g) => round($g->sum('amount'), 2));
        $funds = Fund::lockForUpdate()->whereIn('id', $perFund->keys())->get()->keyBy('id');
        foreach ($perFund as $fid => $need) {
            $f = $funds->get($fid);
            abort_if(! $f || $f->status !== 'active', 422, 'Fund #'.$fid.' is not active.');
            abort_if($need > (float) $f->available_amount, 422, $f->fund_name.' can only cover P'.number_format($f->available_amount, 2).' (needs P'.number_format($need, 2).').');
        }

        $count = 0;
        DB::transaction(function () use ($data, $rows, $perFund, &$count) {
            foreach ($rows as $row) {
                FundAllocation::create([
                    'fund_id' => $row['fund_id'],
                    'budget_plan_id' => $data['budget_plan_id'],
                    'allocated_to' => $data['allocated_to'],
                    'amount' => $row['amount'],
                    'allocation_date' => $data['allocation_date'],
                    'remarks' => $data['remarks'] ?? null,
                    'status' => 'allocated',
                    'created_by' => auth()->id(),
                ]);
                $count++;
            }
            foreach ($perFund as $fid => $need) {
                Fund::whereKey($fid)->decrement('current_balance', $need);
            }
        });
        AuditService::log('allocate', 'fund_allocations', (string) $data['budget_plan_id'], null, null, "Admin ".auth()->user()->name." auto-split Budget #{$data['budget_plan_id']} across {$count} fund source(s), total P".number_format($total, 2).".");
        return back()->with('success', "Budget split recorded: {$count} allocation(s), total P".number_format($total, 2).'.');
    }

    /** Approve an accountant-submitted allocation — same record, submitted → approved (reserve). */
    public function approveAllocation(Request $request, FundAllocation $allocation)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($allocation->status, ['submitted', 'under_review'], true), 422, 'Only submitted allocations can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        DB::transaction(function () use ($allocation, $request) {
            $fund = Fund::lockForUpdate()->findOrFail($allocation->fund_id);
            $available = (float) bcsub((string) $fund->current_balance, (string) $fund->reserved_amount, 2);
            abort_if((float) $allocation->amount > $available, 422, 'Insufficient available fund. Available: P'.number_format($available, 2));
            // The linked budget must still be approved with room left at approval time.
            if ($allocation->budget_plan_id) {
                $budget = BudgetPlan::lockForUpdate()->findOrFail($allocation->budget_plan_id);
                abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Linked budget is no longer approved (Budget #'.$budget->id.' is '.$budget->status.').');
                abort_if((float) $allocation->amount > (float) $budget->remaining_amount, 422, 'Allocation exceeds remaining budget of P'.number_format($budget->remaining_amount, 2).'.');
            }
            $allocation->update([
                'status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
                'approved_by' => auth()->id(), 'approved_at' => now(),
                'admin_remarks' => $request->input('admin_remarks') ?: $allocation->admin_remarks,
            ]);
            $fund->increment('reserved_amount', $allocation->amount);
            FundTransaction::create([
                'fund_id' => $fund->id, 'transaction_type' => 'reservation',
                'amount' => $allocation->amount, 'transaction_date' => today(),
                'reference_number' => 'ALLOC-'.$allocation->id,
                'description' => 'Reserved for allocation #'.$allocation->id.' ('.$allocation->allocated_to.')',
                'created_by' => auth()->id(),
            ]);
        });
        AuditService::log('approve', 'fund_allocations', (string) $allocation->id, ['status' => 'submitted'], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Fund Allocation #{$allocation->id} — now in Fund Allocation Overview.");
        \App\Services\WorkflowService::notifyUser($allocation->created_by, "Fund Allocation #{$allocation->id} has been approved.", "Allocation to '{$allocation->allocated_to}' is now available in the overview.");
        return back()->with('success', 'Allocation approved and reserved. Visible as approved in both modules.');
    }

    /** Reject — same record, submitted → rejected (reason required). */
    public function rejectAllocation(Request $request, FundAllocation $allocation)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($allocation->status, ['submitted', 'under_review'], true), 422, 'Only submitted allocations can be rejected.');
        $allocation->update([
            'status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $allocation->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'revision_number' => ((int) $allocation->revision_number) + 1,
        ]);
        AuditService::log('reject', 'fund_allocations', (string) $allocation->id, ['status' => 'submitted'], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Fund Allocation #{$allocation->id}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($allocation->created_by, "Fund Allocation #{$allocation->id} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit.");
        return back()->with('success', 'Allocation rejected and returned for revision.');
    }
}
