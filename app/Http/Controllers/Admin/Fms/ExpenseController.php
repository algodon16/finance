<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::with('sourcePayable')->orderBy('expense_date', 'desc');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")->orWhere('payee', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%"));
        }
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
        if ($request->filled('payment_status')) $q->where('payment_status', $request->payment_status);
        if ($request->filled('expense_category')) $q->where('expense_category', $request->expense_category);
        if ($request->filled('origin') && $request->origin === 'auto') $q->whereNotNull('related_payable_id');
        if ($request->filled('origin') && $request->origin === 'manual') $q->whereNull('related_payable_id');
        if ($request->filled('date_from')) $q->whereDate('expense_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('expense_date', '<=', $request->date_to);
        $records = $q->paginate(15)->withQueryString();

        // Each peso counted ONCE: superseded proposals (with an approved AP) are excluded,
        // their auto-generated disbursement carries the amount instead.
        $summary = [
            'total' => (float) Expense::financiallyActive()->where('approval_status', 'approved')->sum('amount'),
            'pending' => (float) Expense::manual()->where('approval_status', 'pending')->sum('amount'),
            'paid' => (float) Expense::financiallyActive()->where('payment_status', 'paid')->sum('amount'),
            'monthly' => (float) Expense::financiallyActive()->where('approval_status', 'approved')->whereYear('expense_date', today()->year)->whereMonth('expense_date', today()->month)->sum('amount'),
        ];
        $byDept = Expense::financiallyActive()->where('approval_status', 'approved')->select('department', DB::raw('SUM(amount) as t'))->groupBy('department')->get();
        $byCat = Expense::financiallyActive()->where('approval_status', 'approved')->select('expense_category', DB::raw('SUM(amount) as t'))->groupBy('expense_category')->get();

        return view('admin.fms.expenses.index', compact('records', 'summary', 'byDept', 'byCat'));
    }

    public function create()
    {
        return view('admin.fms.expenses.form', [
            'record' => new Expense(),
            'budgets' => BudgetPlan::orderBy('budget_name')->get(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category' => 'required|string|max:100',
            'department' => 'nullable|string|max:255',
            'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'expense_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'required|string|max:100|unique:expenses,reference_number',
            'description' => 'nullable|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'fund_id' => 'nullable|exists:funds,id',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('expense-docs', 'public');
        }
        $data['created_by'] = auth()->id();
        $record = Expense::create($data);
        AuditService::log('create', 'expenses', (string) $record->id, null, null, "Admin ".auth()->user()->name." recorded Expense #{$record->reference_number} (P".number_format($record->amount, 2).").");
        return redirect()->route('admin.expenses.index')->with('success', 'Expense recorded.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['budgetPlan', 'fund', 'allocation', 'sourcePayable.expense', 'sourcePayable.financialRequest', 'sourcePayable.budgetPlan', 'creator']);
        return view('admin.fms.expenses.show', ['record' => $expense]);
    }

    public function edit(Expense $expense)
    {
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements cannot be edited directly. Adjust the linked AP instead.');
        return view('admin.fms.expenses.form', [
            'record' => $expense,
            'budgets' => BudgetPlan::orderBy('budget_name')->get(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements cannot be edited directly. Adjust the linked AP instead.');
        $data = $request->validate([
            'expense_category' => 'required|string|max:100',
            'department' => 'nullable|string|max:255',
            'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'expense_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'required|string|max:100|unique:expenses,reference_number,'.$expense->id,
            'description' => 'nullable|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'fund_id' => 'nullable|exists:funds,id',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('expense-docs', 'public');
        }
        $old = $expense->toArray();
        $expense->update($data);
        AuditService::log('update', 'expenses', (string) $expense->id, $old, $expense->fresh()->toArray(), "Admin ".auth()->user()->name." updated Expense #{$expense->reference_number}.");
        return redirect()->route('admin.expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function setStatus(Request $request, Expense $expense, string $action)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_unless(in_array($action, ['approve', 'reject', 'pay']), 404);
        if ($action === 'pay') {
            $request->validate([
                'payment_date' => 'required|date|before_or_equal:today',
                'payment_method' => 'required|in:'.implode(',', Expense::PAYMENT_METHODS),
                'payment_reference' => 'nullable|string|max:100',
                'proof_of_payment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);
        } elseif ($action === 'reject') {
            $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        } else {
            $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        }
        if (in_array($action, ['approve', 'reject'])) {
            abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements are approved with their AP and cannot be re-reviewed here.');
            $current = $expense->approval_status ?? 'draft';
            abort_if(! in_array($current, ['submitted', 'under_review'], true), 422, 'Only submitted proposals can be reviewed.');
        }
        $old = $expense->toArray();
        $proofPath = $request->hasFile('proof_of_payment')
            ? $request->file('proof_of_payment')->store('disbursement-proofs', 'public')
            : null;
        DB::transaction(function () use ($request, $expense, $action, $proofPath) {
            if ($action === 'approve') {
                // Re-validate budget server-side at approval time.
                if ($expense->budget_plan_id) {
                    $budget = BudgetPlan::lockForUpdate()->findOrFail($expense->budget_plan_id);
                    abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Linked budget is not approved/active.');
                    $remaining = (float) bcsub((string) $budget->allocated_amount, (string) $budget->utilized_amount, 2);
                    abort_if((float) $expense->amount > $remaining, 422, 'Expense exceeds remaining budget of P'.number_format($remaining, 2).'.');
                }
                $expense->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'admin_remarks' => $request->input('admin_remarks') ?: $expense->admin_remarks]);
                // Auto-utilization: an approved expense consumes its linked budget.
                // (AP auto-disbursements are handled by ApDisbursementService instead.)
                if ($expense->budget_plan_id && ! $expense->related_payable_id) {
                    $budget = BudgetPlan::lockForUpdate()->find($expense->budget_plan_id);
                    if ($budget && in_array($budget->status, ['approved', 'active'], true)) {
                        $budget->increment('utilized_amount', $expense->amount);
                    }
                }
            }
            if ($action === 'reject') {
                $expense->update(['approval_status' => 'rejected', 'rejection_reason' => $request->input('rejection_reason'), 'admin_remarks' => $request->input('admin_remarks') ?: $expense->admin_remarks, 'approved_by' => null, 'approved_at' => null, 'reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'revision_number' => ((int) $expense->revision_number) + 1]);
            }
            if ($action === 'pay') {
                abort_if($expense->approval_status !== 'approved', 422, 'Only approved expenses can be paid.');
                abort_if(in_array($expense->payment_status, ['paid'], true), 422, 'This record is already paid.');
                if ($expense->related_payable_id) {
                    // Auto disbursement: settle the AP's remaining balance so both become Paid together.
                    \App\Services\ApDisbursementService::payViaExpense($expense->fresh(), [
                        'payment_date' => $request->input('payment_date'),
                        'payment_method' => $request->input('payment_method'),
                        'payment_reference' => $request->input('payment_reference'),
                        'proof_of_payment' => $proofPath,
                    ]);
                } else {
                    $expense->update([
                        'payment_status' => 'paid',
                        'payment_date' => $request->input('payment_date'),
                        'payment_method' => $request->input('payment_method'),
                        'payment_reference' => $request->input('payment_reference'),
                        'proof_of_payment' => $proofPath ?? $expense->proof_of_payment,
                        'paid_by' => auth()->id(), 'paid_at' => now(),
                    ]);
                    if ($expense->fund_id) {
                        $fund = Fund::lockForUpdate()->find($expense->fund_id);
                        if ($fund) {
                            abort_if((float) $fund->current_balance < (float) $expense->amount, 422, 'Insufficient fund balance.');
                            $fund->decrement('current_balance', $expense->amount);
                            \App\Models\FundTransaction::create([
                                'fund_id' => $fund->id, 'transaction_type' => 'outflow',
                                'amount' => $expense->amount, 'transaction_date' => today(),
                                'reference_number' => $expense->reference_number,
                                'description' => 'Disbursement for '.$expense->reference_number,
                                'created_by' => auth()->id(),
                            ]);
                        }
                    }
                }
            }
        });
        $ref = $expense->reference_number;
        if ($action === 'approve') {
            AuditService::log('approve', 'expenses', (string) $expense->id, ['status' => $old['approval_status'] ?? null], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Expense Proposal #{$ref} — reflected in Expense Overview, dashboard and reports.");
            \App\Services\WorkflowService::notifyUser($expense->created_by, "Expense Proposal #{$ref} has been approved.", "It is now reflected in the Expense Overview and financial reports.");
        } elseif ($action === 'reject') {
            AuditService::log('reject', 'expenses', (string) $expense->id, ['status' => $old['approval_status'] ?? null], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Expense Proposal #{$ref}: {$request->input('rejection_reason')}");
            \App\Services\WorkflowService::notifyUser($expense->created_by, "Expense Proposal #{$ref} was rejected. Reason: {$request->input('rejection_reason')}", "Revise the same record and resubmit.");
        } else {
            AuditService::log($action, 'expenses', (string) $expense->id, $old, null, "Admin ".auth()->user()->name." {$action}d Expense #{$ref}.");
        }
        return back()->with('success', 'Expense '.$action.'d.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->related_payable_id, 422, 'Auto-generated disbursements cannot be deleted directly. Cancel the linked AP instead.');
        abort_if($expense->payment_status === 'paid', 422, 'Paid expenses cannot be deleted.');
        $old = $expense->toArray();
        DB::transaction(function () use ($expense) {
            // Release the budget consumed at approval time.
            if (($expense->approval_status ?? null) === 'approved' && $expense->budget_plan_id) {
                $budget = BudgetPlan::lockForUpdate()->find($expense->budget_plan_id);
                if ($budget) {
                    $new = max(0.0, (float) $budget->utilized_amount - (float) $expense->amount);
                    $budget->update(['utilized_amount' => $new]);
                }
            }
            $expense->delete();
        });
        AuditService::log('delete', 'expenses', (string) $expense->id, $old, null, "Admin ".auth()->user()->name." deleted Expense #{$old['reference_number']}.");
        return redirect()->route('admin.expenses.index')->with('success', 'Expense deleted.');
    }
}
