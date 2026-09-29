<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Models\Fund;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetPlanController extends Controller
{
    public function index(Request $request)
    {
        $q = BudgetPlan::with(['creator', 'approver'])->orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%")->orWhere('budget_category', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('fiscal_year')) $q->where('fiscal_year', $request->fiscal_year);
        $plans = $q->paginate(12)->withQueryString();
        $years = BudgetPlan::select('fiscal_year')->distinct()->orderBy('fiscal_year')->pluck('fiscal_year');
        $summary = [
            'draft' => (int) BudgetPlan::where('status', 'draft')->count(),
            'pending' => (int) BudgetPlan::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (int) BudgetPlan::whereIn('status', ['approved', 'active'])->count(),
            'revision' => (int) BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->count(),
            'total' => (float) BudgetPlan::sum('allocated_amount'),
        ];

        return view('accountant.budgets.index', compact('plans', 'summary', 'years'));
    }

    public function create()
    {
        $funds = Fund::where('status', 'active')->orderBy('fund_name')->get();
        $availableFunds = (float) $funds->sum(fn($f) => (float) $f->available_amount);
        return view('accountant.budgets.form', ['plan' => new BudgetPlan(), 'funds' => $funds, 'availableFunds' => $availableFunds]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'department' => 'required|string|max:255',
            'budget_category' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'justification' => 'nullable|string|max:5000',
            'funding_source' => 'required|string|max:255',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        // Total is entered directly — line items now live in Procurement and Financial Requests.
        $total = number_format((float) $data['allocated_amount'], 2, '.', '');
        abort_if((float) $total <= 0, 422, 'Total proposed budget must be greater than 0.');
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('budget-docs', 'public');
        }
        $plan = DB::transaction(function () use ($data, $total, $request) {
            $plan = BudgetPlan::create([
                'budget_name' => $data['budget_name'], 'fiscal_year' => $data['fiscal_year'],
                'department' => $data['department'], 'budget_category' => $data['budget_category'],
                'allocated_amount' => $total, 'utilized_amount' => 0,
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                'status' => 'draft', 'description' => ($data['description'] ?? '').(($data['notes'] ?? '') ? "\nNotes: ".$data['notes'] : ''),
                'justification' => $data['justification'] ?? null, 'funding_source' => $data['funding_source'],
                'supporting_document' => $data['supporting_document'] ?? null,
                'created_by' => auth()->id(),
            ]);
            return $plan;
        });
        AuditService::log('create', 'budget_plans', (string) $plan->id, null, null, "Accountant ".auth()->user()->name." prepared Budget Plan #{$plan->id} ({$plan->budget_name}) total P".number_format($total, 2).".");

        if ($request->input('action') === 'submit') {
            WorkflowService::submit($plan->fresh(), 'budget_plans', 'Budget Plan');
            return redirect()->route('accountant.budgets.index')->with('success', 'Budget plan submitted for admin approval.');
        }
        return redirect()->route('accountant.budgets.index')->with('success', 'Budget plan saved as draft.');
    }

    public function show(BudgetPlan $budget)
    {
        $budget->load(['items', 'allocations', 'creator', 'approver', 'submitter', 'reviewer']);
        $funds = Fund::where('status', 'active')->get();
        $availableFunds = (float) $funds->sum(fn($f) => (float) $f->available_amount);
        $history = AuditLog::where('module', 'budget_plans')->where('record_id', (string) $budget->id)->latest('id')->take(20)->get();
        return view('accountant.budgets.show', ['plan' => $budget, 'availableFunds' => $availableFunds, 'history' => $history]);
    }

    public function edit(BudgetPlan $budget)
    {
        abort_if(! in_array($budget->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Only draft or rejected/revision budgets can be edited.');
        $budget->load('items');
        $funds = Fund::where('status', 'active')->orderBy('fund_name')->get();
        $availableFunds = (float) $funds->sum(fn($f) => (float) $f->available_amount);
        return view('accountant.budgets.form', ['plan' => $budget, 'funds' => $funds, 'availableFunds' => $availableFunds]);
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($budget->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted budgets cannot be edited directly. Revise after rejection.');
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'department' => 'required|string|max:255',
            'budget_category' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'justification' => 'nullable|string|max:5000',
            'funding_source' => 'required|string|max:255',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        $total = number_format((float) $data['allocated_amount'], 2, '.', '');
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('budget-docs', 'public');
        }
        $old = $budget->toArray();
        DB::transaction(function () use ($budget, $data, $total) {
            $budget->update([
                'budget_name' => $data['budget_name'], 'fiscal_year' => $data['fiscal_year'],
                'department' => $data['department'], 'budget_category' => $data['budget_category'],
                'allocated_amount' => $total,
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                'status' => 'draft', 'description' => ($data['description'] ?? '').(($data['notes'] ?? '') ? "\nNotes: ".$data['notes'] : ''),
                'justification' => $data['justification'] ?? null, 'funding_source' => $data['funding_source'],
                'supporting_document' => $data['supporting_document'] ?? $budget->supporting_document,
                'rejection_reason' => null,
                'revision_number' => ((int) $budget->revision_number) + 1,
            ]);
        });
        AuditService::log('update', 'budget_plans', (string) $budget->id, $old, $budget->fresh()->toArray(), "Accountant ".auth()->user()->name." revised Budget Plan #{$budget->id} (rev ".((int) $budget->revision_number + 1)."). Original history preserved.");
        return redirect()->route('accountant.budgets.show', $budget)->with('success', 'Budget plan revised and saved as draft. Submit for admin approval when ready.');
    }

    public function submit(BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403, 'Accountant cannot approve own submission.');
        abort_if((float) $budget->allocated_amount <= 0, 422, 'Enter a total proposed amount greater than 0 before submitting.');
        // One official budget per department + year — drafts stay exempt, submission is the gate.
        if ($dup = BudgetPlan::officialDuplicateExists($budget->department, $budget->fiscal_year, $budget->id)) {
            return back()->withErrors(['department' => 'Department "'.$budget->department.'" already has an official budget for '.$budget->fiscal_year.' (BUD-'.$dup->id.' — '.$dup->status.'). Revise that record instead of submitting a duplicate.']);
        }
        WorkflowService::submit($budget, 'budget_plans', 'Budget Plan');
        return back()->with('success', 'Budget plan submitted for admin approval.');
    }

    public function cancel(BudgetPlan $budget)
    {
        // Withdraw a pending submission back to draft.
        abort_unless(auth()->user()->role === 'accountant', 403);
        WorkflowService::cancelSubmission($budget, 'budget_plans', 'Budget Plan');
        return back()->with('success', 'Submission withdrawn. Record returned to draft.');
    }

    public function duplicate(BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $copy = DB::transaction(function () use ($budget) {
            $new = $budget->replicate(['approved_by', 'approved_at', 'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by']);
            $new->budget_name = $budget->budget_name.' (Copy)';
            $new->status = 'draft';
            $new->utilized_amount = 0;
            $new->rejection_reason = null;
            $new->admin_remarks = null;
            $new->revision_number = 0;
            $new->created_by = auth()->id();
            $new->push();
            return $new;
        });
        AuditService::log('create', 'budget_plans', (string) $copy->id, null, null, "Accountant ".auth()->user()->name." duplicated Budget Plan #{$budget->id} as #{$copy->id}.");
        return redirect()->route('accountant.budgets.edit', $copy)->with('success', 'Budget duplicated as draft.');
    }

    public function destroyDraft(BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($budget->status, ['draft', 'cancelled'], true), 422, 'Only draft/cancelled plans can be removed.');
        $old = $budget->toArray();
        DB::transaction(function () use ($budget) {
            // Legacy line items (pre-move to Financial Requests) are preserved, not deleted.
            $budget->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
        AuditService::log('cancel', 'budget_plans', (string) $budget->id, $old, null, "Accountant ".auth()->user()->name." cancelled draft Budget Plan #{$budget->id}.");
        return redirect()->route('accountant.budgets.index')->with('success', 'Draft cancelled (history preserved).');
    }
}
