<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class ExpenseProposalController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::with(['budgetPlan', 'fund', 'allocation', 'sourcePayable'])->orderByDesc('created_at');
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
        if ($request->filled('origin') && $request->origin === 'auto') $q->whereNotNull('related_payable_id');
        if ($request->filled('origin') && $request->origin === 'manual') $q->whereNull('related_payable_id');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")->orWhere('payee', 'ilike', "%{$s}%"));
        }
        $records = $q->paginate(12)->withQueryString();
        $summary = [
            'draft' => (int) Expense::manual()->where('approval_status', 'draft')->count(),
            'pending' => (int) Expense::manual()->whereIn('approval_status', ['submitted', 'under_review'])->count(),
            'approved' => (int) Expense::financiallyActive()->where('approval_status', 'approved')->count(),
            'total' => (float) Expense::financiallyActive()->where('approval_status', 'approved')->sum('amount'),
            'auto' => (int) Expense::autoDisbursement()->count(),
        ];
        return view('accountant.expenses.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('accountant.expenses.form', [
            'record' => new Expense(),
            'budgets' => BudgetPlan::whereIn('status', ['approved', 'active'])->orderBy('budget_name')->get(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
            'allocations' => FundAllocation::where('status', 'approved')->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'expense_category' => 'required|string|max:100',
            'department' => 'required|string|max:255',
            'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'expense_date' => 'required|date|before_or_equal:today',
            'proposed_payment_date' => 'nullable|date|after_or_equal:expense_date',
            'description' => 'required|string|max:5000',
            'justification' => 'nullable|string|max:5000',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'fund_id' => 'nullable|exists:funds,id',
            'fund_allocation_id' => 'nullable|exists:fund_allocations,id',
            'fund_source' => 'nullable|string|max:255',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ]);
        // Prevent spending beyond approved budget.
        if (! empty($data['budget_plan_id'])) {
            $budget = BudgetPlan::findOrFail($data['budget_plan_id']);
            abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Expense must reference an approved/active budget.');
            $remaining = (float) bcsub((string) $budget->allocated_amount, (string) $budget->utilized_amount, 2);
            if ((float) $data['amount'] > $remaining) {
                return back()->withErrors(['amount' => 'Current Proposal exceeds Remaining Budget of P'.number_format($remaining, 2).'.'])->withInput();
            }
        }
        // If linked allocation, validate against allocation amount.
        // Count each peso once: superseded proposals (with an approved AP) are excluded,
        // their auto disbursement carries the amount instead.
        if (! empty($data['fund_allocation_id'])) {
            $alloc = FundAllocation::findOrFail($data['fund_allocation_id']);
            abort_if($alloc->status !== 'approved', 422, 'Expense must link an approved allocation.');
            $used = (float) Expense::financiallyActive()->where('fund_allocation_id', $alloc->id)->where('approval_status', 'approved')->sum('amount');
            $allocRemaining = (float) $alloc->amount - $used;
            if ((float) $data['amount'] > $allocRemaining) {
                return back()->withErrors(['amount' => 'Current Proposal exceeds remaining allocation of P'.number_format($allocRemaining, 2).'.'])->withInput();
            }
        }
        $data['reference_number'] = 'EXP-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('expense-docs', 'public');
        }
        // Accountant proposals always start as pending/draft — never auto-approved.
        $data['approval_status'] = 'draft';
        $data['payment_status'] = 'pending';
        $data['created_by'] = auth()->id();
        unset($data['remarks']);
        $record = Expense::create($data);
        AuditService::log('create', 'expenses', (string) $record->id, null, null, "Accountant ".auth()->user()->name." prepared Expense Proposal #{$record->reference_number} (P".number_format($record->amount, 2).").");
        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'expenses', 'Expense Proposal', 'approval_status');
            return redirect()->route('accountant.expenses.index')->with('success', 'Expense proposal submitted for admin approval.');
        }
        return redirect()->route('accountant.expenses.index')->with('success', 'Expense proposal saved as draft.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['budgetPlan', 'fund', 'allocation', 'relatedPayable', 'sourcePayable.expense', 'sourcePayable.financialRequest', 'sourcePayable.budgetPlan', 'sourcePayable.allocation']);
        $history = AuditLog::where('module', 'expenses')->where('record_id', (string) $expense->id)->latest('id')->take(20)->get();
        $autoHistory = collect();
        if ($expense->related_payable_id) {
            $autoHistory = AuditLog::where('module', 'accounts_payable')->where('record_id', (string) $expense->related_payable_id)->latest('id')->take(20)->get();
        }
        $impact = null;
        if ($expense->budget_plan_id) {
            $b = $expense->budgetPlan;
            $allocated = (float) FundAllocation::where('budget_plan_id', $b->id)->where('status', 'approved')->sum('amount');
            $used = (float) Expense::financiallyActive()->where('budget_plan_id', $b->id)->where('approval_status', 'approved')->where('id', '!=', $expense->id)->sum('amount');
            $remaining = (float) bcsub(bcsub((string) $b->allocated_amount, (string) $b->utilized_amount, 2), (string) 0, 2);
            $remaining = (float) bcsub((string) $b->allocated_amount, (string) $b->utilized_amount, 2);
            $impact = [
                'approved' => (float) $b->allocated_amount,
                'allocated' => $allocated,
                'used' => $used + (float) $b->utilized_amount,
                'remaining' => $remaining,
                'proposal' => (float) $expense->amount,
                'after' => $remaining - (float) $expense->amount,
            ];
        }
        return view('accountant.expenses.show', ['record' => $expense, 'impact' => $impact, 'history' => $history, 'autoHistory' => $autoHistory]);
    }

    public function edit(Expense $expense)
    {
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements cannot be edited. Adjust the linked AP instead.');
        abort_if(! in_array($expense->approval_status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Only draft or rejected/revision proposals can be edited.');
        return view('accountant.expenses.form', [
            'record' => $expense,
            'budgets' => BudgetPlan::whereIn('status', ['approved', 'active'])->orderBy('budget_name')->get(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
            'allocations' => FundAllocation::where('status', 'approved')->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements cannot be edited. Adjust the linked AP instead.');
        abort_if(! in_array($expense->approval_status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted expenses cannot be edited directly.');
        $data = $request->validate([
            'expense_category' => 'required|string|max:100',
            'department' => 'required|string|max:255',
            'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'expense_date' => 'required|date|before_or_equal:today',
            'proposed_payment_date' => 'nullable|date|after_or_equal:expense_date',
            'description' => 'required|string|max:5000',
            'justification' => 'nullable|string|max:5000',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'fund_id' => 'nullable|exists:funds,id',
            'fund_source' => 'nullable|string|max:255',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        if (! empty($data['budget_plan_id'])) {
            $budget = BudgetPlan::findOrFail($data['budget_plan_id']);
            $remaining = (float) bcsub((string) $budget->allocated_amount, (string) $budget->utilized_amount, 2);
            if ((float) $data['amount'] > $remaining) {
                return back()->withErrors(['amount' => 'Expense exceeds remaining budget of P'.number_format($remaining, 2).'.'])->withInput();
            }
        }
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('expense-docs', 'public');
        }
        $old = $expense->toArray();
        $data['approval_status'] = 'draft';
        $data['rejection_reason'] = null;
        $expense->update($data);
        AuditService::log('update', 'expenses', (string) $expense->id, $old, $expense->fresh()->toArray(), "Accountant ".auth()->user()->name." revised Expense Proposal #{$expense->reference_number}.");
        return redirect()->route('accountant.expenses.show', $expense)->with('success', 'Proposal revised and saved as draft.');
    }

    public function submit(Expense $expense)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements follow their AP and cannot be submitted separately.');
        WorkflowService::submit($expense, 'expenses', 'Expense Proposal', 'approval_status');
        return back()->with('success', 'Expense proposal submitted for admin approval.');
    }

    public function cancel(Expense $expense)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements follow their AP and cannot be withdrawn separately.');
        abort_if(! in_array($expense->approval_status, ['submitted', 'under_review'], true), 422, 'Only pending submissions can be cancelled.');
        $expense->update(['approval_status' => 'draft', 'submitted_at' => null]);
        AuditService::log('cancel', 'expenses', (string) $expense->id, null, null, "Accountant ".auth()->user()->name." cancelled submission of Expense Proposal #{$expense->reference_number}.");
        return back()->with('success', 'Submission cancelled.');
    }
}
