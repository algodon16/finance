<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class FundAllocationController extends Controller
{
    public function index(Request $request)
    {
        $q = FundAllocation::with(['fund', 'budgetPlan'])->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('fund_id')) $q->where('fund_id', $request->fund_id);
        $records = $q->paginate(12)->withQueryString();
        $funds = Fund::orderBy('fund_name')->get();
        return view('accountant.fund-allocations.index', compact('records', 'funds'));
    }

    public function create()
    {
        return view('accountant.fund-allocations.form', [
            'record' => new FundAllocation(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
            'budgets' => BudgetPlan::whereIn('status', ['approved', 'active'])->orderBy('budget_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'fund_id' => 'required|exists:funds,id',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'allocated_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_date' => 'required|date|before_or_equal:today',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ]);
        // Server-side: do not allow exceeding available funds.
        $fund = Fund::findOrFail($data['fund_id']);
        $available = (float) bcsub((string) $fund->current_balance, (string) $fund->reserved_amount, 2);
        if ((float) $data['amount'] > $available) {
            return back()->withErrors(['amount' => 'Allocation exceeds available fund of P'.number_format($available, 2).'.'])->withInput();
        }
        // Fund Management draws only on approved budgets — never on drafts.
        if (! empty($data['budget_plan_id'])) {
            $err = $this->budgetGuard((int) $data['budget_plan_id'], (float) $data['amount']);
            if ($err) return back()->withErrors(['budget_plan_id' => $err])->withInput();
        }
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('allocation-docs', 'public');
        }
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();
        $record = FundAllocation::create($data);
        AuditService::log('create', 'fund_allocations', (string) $record->id, null, null, "Accountant ".auth()->user()->name." prepared Fund Allocation #{$record->id} (P".number_format($record->amount, 2).").");
        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'fund_allocations', 'Fund Allocation');
            return redirect()->route('accountant.fund-allocations.index')->with('success', 'Fund allocation submitted for admin approval.');
        }
        return redirect()->route('accountant.fund-allocations.index')->with('success', 'Fund allocation saved as draft.');
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
        abort_if(! in_array($fundAllocation->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Only draft or rejected/revision allocations can be edited.');
        return view('accountant.fund-allocations.form', [
            'record' => $fundAllocation,
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
            'budgets' => BudgetPlan::whereIn('status', ['approved', 'active'])->orderBy('budget_name')->get(),
        ]);
    }

    public function update(Request $request, FundAllocation $fundAllocation)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($fundAllocation->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted allocations cannot be edited directly.');
        $data = $request->validate([
            'fund_id' => 'required|exists:funds,id',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'allocated_to' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_date' => 'required|date|before_or_equal:today',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $fund = Fund::findOrFail($data['fund_id']);
        $available = (float) bcsub((string) $fund->current_balance, (string) $fund->reserved_amount, 2);
        if ((float) $data['amount'] > $available) {
            return back()->withErrors(['amount' => 'Allocation exceeds available fund of P'.number_format($available, 2).'.'])->withInput();
        }
        if (! empty($data['budget_plan_id'])) {
            $err = $this->budgetGuard((int) $data['budget_plan_id'], (float) $data['amount']);
            if ($err) return back()->withErrors(['budget_plan_id' => $err])->withInput();
        }
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('allocation-docs', 'public');
        }
        $old = $fundAllocation->toArray();
        $data['status'] = 'draft';
        $data['rejection_reason'] = null;
        $data['revision_number'] = ((int) $fundAllocation->revision_number) + 1;
        $fundAllocation->update($data);
        AuditService::log('update', 'fund_allocations', (string) $fundAllocation->id, $old, $fundAllocation->fresh()->toArray(), "Accountant ".auth()->user()->name." revised Fund Allocation #{$fundAllocation->id}.");
        return redirect()->route('accountant.fund-allocations.show', $fundAllocation)->with('success', 'Allocation revised and saved as draft.');
    }

    public function submit(FundAllocation $fundAllocation)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        // Re-validate available fund at submit time.
        $fund = Fund::findOrFail($fundAllocation->fund_id);
        $available = (float) bcsub((string) $fund->current_balance, (string) $fund->reserved_amount, 2);
        abort_if((float) $fundAllocation->amount > $available, 422, 'Allocation exceeds available fund of P'.number_format($available, 2).'.');
        // Re-validate the linked budget: still approved with room left.
        if ($fundAllocation->budget_plan_id) {
            $err = $this->budgetGuard((int) $fundAllocation->budget_plan_id, (float) $fundAllocation->amount);
            abort_if($err, 422, $err);
        }
        WorkflowService::submit($fundAllocation, 'fund_allocations', 'Fund Allocation');
        return back()->with('success', 'Fund allocation submitted for admin approval.');
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
