<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class ExpenseProposalController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::with(['budgetPlan', 'fund', 'allocation', 'sourcePayable.financialRequest'])
            ->whereNotNull('related_payable_id')
            ->orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")->orWhere('payee', 'ilike', "%{$s}%"));
        }
        if ($request->filled('payment_status')) $q->where('payment_status', $request->payment_status);
        $records = $q->paginate(12)->withQueryString();
        $summary = [
            'for_processing' => (int) Expense::autoDisbursement()->where('payment_status', 'for_disbursement')->count(),
            'completed' => (int) Expense::autoDisbursement()->where('payment_status', 'paid')->count(),
            'total' => (float) Expense::financiallyActive()->where('approval_status', 'approved')->sum('amount'),
        ];
        return view('accountant.expenses.index', compact('records', 'summary'));
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
            $remaining = (float) bcsub((string) $b->allocated_amount, (string) $b->utilized_amount, 2);
            $impact = [
                'approved' => (float) $b->allocated_amount,
                'used' => (float) $b->utilized_amount,
                'remaining' => $remaining,
                'proposal' => (float) $expense->amount,
                'after' => $remaining - (float) $expense->amount,
            ];
        }
        return view('accountant.expenses.show', ['record' => $expense, 'impact' => $impact, 'history' => $history, 'autoHistory' => $autoHistory]);
    }

    /**
     * Record the ACTUAL expense for an auto-generated disbursement linked to an
     * approved Financial Request. The approved amount is only the authorization
     * ceiling — the actual transacted amount is recorded here (never auto-filled).
     */
    public function recordActual(Request $request, Expense $expense)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! $expense->related_payable_id, 422, 'Only auto-generated disbursements use actual recording.');
        abort_if($expense->approval_status !== 'approved', 422, 'Only approved disbursements can be processed.');
        abort_if($expense->payment_status === 'paid', 422, 'Completed disbursements cannot be changed.');
        $expense->loadMissing(['sourcePayable.financialRequest']);
        $ap = $expense->sourcePayable;
        abort_if(! $ap || ($ap->approval_status ?? 'draft') !== 'approved', 422, 'The linked payable must be approved.');
        $fr = $ap->financialRequest;
        abort_if(! $fr || $fr->status !== 'approved', 422, 'The linked request must be approved.');

        $data = $request->validate([
            'actual_amount' => 'required|numeric|min:0.01|max:'.(float) $ap->amount,
            'transaction_date' => 'required|date|before_or_equal:today',
            'vendor' => 'nullable|string|max:255',
            'payment_method' => 'required|in:'.implode(',', Expense::PAYMENT_METHODS),
            'payment_reference' => 'nullable|string|max:100',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:60',
        ]);

        abort_if((float) $data['actual_amount'] + 0.009 < (float) $ap->amount_paid, 422, 'Actual cannot be less than the amount already disbursed (P'.number_format($ap->amount_paid, 2).').');

        $old = ['amount' => (string) $expense->amount, 'actual' => $fr->actual_amount];
        $patch = [
            'amount' => $data['actual_amount'],
            'expense_date' => $data['transaction_date'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'],
        ];
        if (! empty($data['vendor'])) $patch['payee'] = $data['vendor'];
        if ($request->hasFile('supporting_document')) {
            $patch['supporting_document'] = $request->file('supporting_document')->store('expense-docs', 'public');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($expense, $ap, $fr, $patch, $data) {
            $expense->update($patch);
            if (! empty($data['vendor'])) $ap->update(['vendor' => $data['vendor']]);
            $meta = is_array($fr->metadata) ? $fr->metadata : [];
            $meta['actual_amount'] = (float) $data['actual_amount'];
            $meta['actual_date'] = $data['transaction_date'];
            if (! empty($data['remarks'])) $meta['actual_remarks'] = $data['remarks'];
            $fr->update(['metadata' => $meta]);
        });

        AuditService::log('record_actual', 'expenses', (string) $expense->id, $old, ['amount' => $data['actual_amount']],
            "Accountant ".auth()->user()->name." recorded actual P".number_format($data['actual_amount'], 2)." for Disbursement {$expense->reference_number} (approved ceiling P".number_format($ap->amount, 2).").");
        AuditService::log('record_actual', 'financial_requests', (string) $fr->id, $old, ['actual_amount' => (float) $data['actual_amount']],
            "Accountant ".auth()->user()->name." recorded actual P".number_format($data['actual_amount'], 2)." for {$fr->display_ref}.");

        return back()->with('success', 'Actual expense recorded. It will post to budget utilization on completion.');
    }

    public function recordData(Expense $expense)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! $expense->related_payable_id, 422, 'Only auto-generated disbursements.');
        $expense->loadMissing(['sourcePayable.financialRequest.budgetPlan']);
        $ap = $expense->sourcePayable;
        $fr = $ap?->financialRequest;
        abort_if(! $fr, 404, 'Financial request not found.');

        return response()->json([
            'success' => true,
            'data' => [
                'request_id' => $fr->source_request_id ?: $fr->request_number,
                'department' => $fr->department,
                'request_type' => ucfirst(str_replace('_', ' ', $fr->request_type)),
                'purpose' => $fr->description,
                'approved_amount' => (float) ($fr->approved_amount ?? $ap->amount),
                'budget_ref' => $fr->budgetPlan?->request_id ? $fr->budgetPlan->request_id . ' — ' . $fr->budgetPlan->budget_name : '',
                'fund_source' => $fr->budgetPlan?->funding_source ?? $fr->budgetPlan?->fund->fund_name ?? '',
                'ap_ref' => $ap->ap_number ?? '',
                'current_amount' => (float) $expense->amount,
            ]
        ]);
    }
}