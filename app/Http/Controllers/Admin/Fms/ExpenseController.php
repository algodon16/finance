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
        $q = Expense::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")->orWhere('payee', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%"));
        }
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
        if ($request->filled('payment_status')) $q->where('payment_status', $request->payment_status);
        if ($request->filled('expense_category')) $q->where('expense_category', $request->expense_category);
        if ($request->filled('date_from')) $q->whereDate('expense_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('expense_date', '<=', $request->date_to);
        $records = $q->orderBy('expense_date', 'desc')->paginate(15)->withQueryString();

        $summary = [
            'total' => (float) Expense::where('approval_status', 'approved')->sum('amount'),
            'pending' => (float) Expense::where('approval_status', 'pending')->sum('amount'),
            'paid' => (float) Expense::where('payment_status', 'paid')->sum('amount'),
            'monthly' => (float) Expense::where('approval_status', 'approved')->whereYear('expense_date', today()->year)->whereMonth('expense_date', today()->month)->sum('amount'),
        ];
        $byDept = Expense::where('approval_status', 'approved')->select('department', DB::raw('SUM(amount) as t'))->groupBy('department')->get();
        $byCat = Expense::where('approval_status', 'approved')->select('expense_category', DB::raw('SUM(amount) as t'))->groupBy('expense_category')->get();

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
        return view('admin.fms.expenses.show', ['record' => $expense]);
    }

    public function edit(Expense $expense)
    {
        return view('admin.fms.expenses.form', [
            'record' => $expense,
            'budgets' => BudgetPlan::orderBy('budget_name')->get(),
            'funds' => Fund::where('status', 'active')->orderBy('fund_name')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
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

    public function setStatus(Expense $expense, string $action)
    {
        abort_unless(in_array($action, ['approve', 'reject', 'pay']), 404);
        $old = $expense->toArray();
        DB::transaction(function () use ($expense, $action) {
            if ($action === 'approve') $expense->update(['approval_status' => 'approved', 'approved_by' => auth()->id()]);
            if ($action === 'reject') $expense->update(['approval_status' => 'rejected', 'approved_by' => auth()->id()]);
            if ($action === 'pay') {
                abort_if($expense->approval_status !== 'approved', 422, 'Only approved expenses can be paid.');
                $expense->update(['payment_status' => 'paid']);
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
        });
        AuditService::log($action, 'expenses', (string) $expense->id, $old, null, "Admin ".auth()->user()->name." {$action}d Expense #{$expense->reference_number}.");
        return back()->with('success', 'Expense '.$action.'d.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->payment_status === 'paid', 422, 'Paid expenses cannot be deleted.');
        $old = $expense->toArray();
        $expense->delete();
        AuditService::log('delete', 'expenses', (string) $expense->id, $old, null, "Admin ".auth()->user()->name." deleted Expense #{$old['reference_number']}.");
        return redirect()->route('admin.expenses.index')->with('success', 'Expense deleted.');
    }
}
