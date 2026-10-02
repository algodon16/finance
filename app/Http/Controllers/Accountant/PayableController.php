<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\PayablePayment;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $q = AccountsPayable::with(['expense', 'financialRequest', 'budgetPlan'])->orderByDesc('created_at');

        if ($request->filled('approval_status')) {
            $q->where('approval_status', $request->approval_status);
        }
        if ($request->filled('due_from')) {
            $q->whereDate('due_date', '>=', $request->due_from);
        }
        if ($request->filled('due_to')) {
            $q->whereDate('due_date', '<=', $request->due_to);
        }
        if ($request->filled('department')) {
            $dept = $request->department;
            $q->where(function ($w) use ($dept) {
                $w->whereHas('expense', fn($e) => $e->where('department', 'ilike', "%{$dept}%"))
                  ->orWhereHas('financialRequest', fn($f) => $f->where('department', 'ilike', "%{$dept}%"));
            });
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('vendor', 'ilike', "%{$s}%")
                ->orWhere('invoice_number', 'ilike', "%{$s}%"));
        }

        $records = $q->paginate(12)->withQueryString();
        $summary = [
            'draft' => (int) AccountsPayable::where('approval_status', 'draft')->count(),
            'pending' => (int) AccountsPayable::whereIn('approval_status', ['submitted', 'under_review'])->count(),
            'approved' => (int) AccountsPayable::where('approval_status', 'approved')->count(),
            'overdue' => (int) AccountsPayable::where('due_date', '<', today())->whereRaw('amount > amount_paid')->count(),
            'total' => (float) AccountsPayable::sum('amount'),
            'paid' => (float) AccountsPayable::sum('amount_paid'),
        ];
        $departments = Expense::whereNotNull('department')->distinct()->pluck('department')
            ->merge(FinancialRequest::whereNotNull('department')->distinct()->pluck('department'))
            ->filter()->unique()->sort()->values();

        return view('accountant.payables.index', compact('records', 'summary', 'departments'));
    }

    /**
     * JSON preview for the selected source transaction.
     * GET /accountant/payables/source-preview?kind=expense|financial_request&id=1
     * or quick lookup by ID / request number:
     * GET /accountant/payables/source-preview?q=FR-20260929-XXXX (kind optional, auto-detected)
     */
    public function sourcePreview(Request $request)
    {
        // Quick lookup: user typed an ID or a request/reference number.
        if ($request->filled('q') && ! $request->filled('id')) {
            $q = trim((string) $request->query('q'));
            $kindHint = $request->query('kind');
            $found = $this->findSourceByQuery($q, $kindHint);
            abort_if(! $found, 404, 'No approved source found for "'.$q.'".');

            return response()->json($this->serializeSource($found['source'], $found['kind']) + [
                'select_value' => $found['kind'].':'.$found['source']->id,
            ]);
        }

        $request->validate([
            'kind' => 'required|in:expense,financial_request',
            'id' => 'required|integer|min:1',
        ]);

        $source = $request->kind === 'expense'
            ? Expense::with(['budgetPlan', 'allocation'])->findOrFail($request->id)
            : FinancialRequest::with('budgetPlan')->findOrFail($request->id);

        return response()->json($this->serializeSource($source, $request->kind) + [
            'select_value' => $request->kind.':'.$source->id,
        ]);
    }

    /**
     * Find an approved source by numeric ID, FR request number, or expense reference.
     * Financial Requests are preferred when the query is ambiguous.
     */
    protected function findSourceByQuery(string $q, ?string $kindHint = null): ?array
    {
        $isExpenseHint = $kindHint === 'expense';
        $isFrHint = $kindHint === 'financial_request';

        if (! $isExpenseHint) {
            $fr = ctype_digit($q)
                ? FinancialRequest::with('budgetPlan')->find((int) $q)
                : FinancialRequest::with('budgetPlan')->where('request_number', $q)->first();
            if ($fr && in_array($fr->status, ['approved', 'completed'], true)) {
                return ['source' => $fr, 'kind' => 'financial_request'];
            }
        }
        if (! $isFrHint) {
            $exp = ctype_digit($q)
                ? Expense::with(['budgetPlan', 'allocation'])->find((int) $q)
                : Expense::with(['budgetPlan', 'allocation'])->where('reference_number', $q)->first();
            if ($exp && $exp->approval_status === 'approved') {
                return ['source' => $exp, 'kind' => 'expense'];
            }
        }
        return null;
    }

    public function show(AccountsPayable $payable)
    {
        $payable->load(['payments', 'budgetPlan', 'fund', 'expense.allocation', 'expense.budgetPlan', 'allocation', 'financialRequest.budgetPlan', 'disbursement']);
        $history = AuditLog::where('module', 'accounts_payable')->where('record_id', (string) $payable->id)->latest('id')->take(20)->get();
        return view('accountant.payables.show', ['record' => $payable, 'history' => $history]);
    }

    /**
     * Record a disbursement/payment against an approved payable.
     * Reuses the AP → Expense sync and fund movement (each peso moved once).
     * Total paid can never exceed the recorded actual (or the approved ceiling).
     */
    public function pay(Request $request, AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(($payable->approval_status ?? 'draft') !== 'approved', 422, 'Only approved payables can be disbursed.');
        $payable->loadMissing(['financialRequest', 'disbursement']);
        // Actual-first rule: the approved ceiling authorizes, but only the recorded
        // actual may be disbursed (FR-flow payables).
        if ($payable->financial_request_id) {
            abort_if(! $payable->financialRequest || ! $payable->financialRequest->actual_amount, 422, 'Record the actual expense amount first.');
        }
        $ceiling = $payable->financialRequest?->actual_amount
            ? min((float) $payable->amount, (float) $payable->financialRequest->actual_amount)
            : (float) $payable->amount;
        $maxPay = max(0, $ceiling - (float) $payable->amount_paid);
        abort_if($maxPay <= 0, 422, 'Nothing left to disburse.');
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.$maxPay,
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($payable, $data) {
            PayablePayment::create($data + ['accounts_payable_id' => $payable->id, 'created_by' => auth()->id()]);
            $payable->increment('amount_paid', $data['amount']);
            $payable->refresh();
            $this->syncPaymentStatus($payable->fresh());
            \App\Services\ApDisbursementService::moveFund($payable->fund_id, (float) $data['amount'], $payable->invoice_number, 'Disbursement for '.$payable->ap_number);
            \App\Services\ApDisbursementService::syncFromPayablePayment($payable->fresh());
        });
        AuditService::log('pay', 'accounts_payable', (string) $payable->id, null, null,
            "Accountant ".auth()->user()->name." recorded P".number_format($data['amount'], 2)." disbursement to {$payable->ap_number} (Ref: ".($data['reference_number'] ?? '—').').');
        return back()->with('success', 'Disbursement recorded and synced to Expense & Disbursement Tracking.');
    }

    /**
     * Complete the financial transaction (accountant only).
     * FR-flow payables only. Requires recorded actual, full disbursement, and
     * supporting documents. Posts ACTUAL (not approved) budget utilization and
     * synchronizes Request → APPROVED→COMPLETED, Expense → COMPLETED.
     */
    public function complete(Request $request, AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);

        $payable->loadMissing(['financialRequest.budgetPlan', 'disbursement', 'payments']);
        $fr = $payable->financialRequest;
        $actual = $fr?->actual_amount;
        $expense = $payable->disbursement;

        // Validate all prerequisites with messages flashed to session
        $errors = [];
        if (($payable->approval_status ?? 'draft') !== 'approved') {
            $errors[] = 'Only approved payables can be completed.';
        }
        if (! $payable->financial_request_id) {
            $errors[] = 'Only requests approved through Procurement & Financial Requests can be completed here.';
        }
        if (! $fr || $fr->status !== 'approved') {
            $errors[] = 'The linked request must be approved (not completed or rejected).';
        }
        if (! $actual || $actual <= 0) {
            $errors[] = 'Record the actual expense amount first in Expense & Disbursement Tracking.';
        }
        if (! $expense) {
            $errors[] = 'Linked disbursement record is missing.';
        }
        $hasDocs = (bool) ($expense?->supporting_document || $expense?->proof_of_payment || $payable->supporting_document);
        if (! $hasDocs) {
            $errors[] = 'Attach a supporting document or proof of payment first.';
        }
        if ($actual && (float) $payable->amount_paid + 0.009 < $actual) {
            $errors[] = 'Disbursement must fully cover the actual amount of P'.number_format($actual, 2).' before completion.';
        }

        if (! empty($errors)) {
            return redirect()->route('accountant.payables.show', $payable)->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($payable, $fr, $expense, $actual) {
            $locked = AccountsPayable::lockForUpdate()->findOrFail($payable->id);
            $unused = round((float) $locked->amount - $actual, 2);
            if ($unused > 0.009) {
                // Release the unused authorization ceiling — only the actual is spent.
                $locked->update(['amount' => $actual]);
            }
            $locked->update(['payment_status' => 'paid']);
            $latest = $locked->payments()->latest('id')->first();
            $expense->update([
                'amount' => $actual,
                'payment_status' => 'paid',
                'payment_date' => $latest->payment_date ?? $expense->payment_date,
                'payment_method' => $latest->payment_method ?? $expense->payment_method,
                'payment_reference' => $latest->reference_number ?? $expense->payment_reference,
                'paid_by' => auth()->id(), 'paid_at' => now(),
            ]);
            $fr->update(['status' => 'completed', 'completed_at' => now()]);
            if ($fr->budget_plan_id && ($budget = BudgetPlan::lockForUpdate()->find($fr->budget_plan_id))) {
                $budget->increment('utilized_amount', $actual);
            }
        });

        AuditService::log('complete', 'accounts_payable', (string) $payable->id, ['payment_status' => $payable->payment_status], ['payment_status' => 'paid'],
            "Accountant ".auth()->user()->name." completed {$payable->ap_number}: actual P".number_format($actual, 2).", unused P".number_format(max(0, (float) $payable->amount - $actual), 2)." released.");
        AuditService::log('complete', 'expenses', (string) $expense->id, null, ['payment_status' => 'paid'],
            "Accountant ".auth()->user()->name." completed Disbursement {$expense->reference_number} (actual P".number_format($actual, 2).').');
        AuditService::log('complete', 'financial_requests', (string) $fr->id, ['status' => 'approved'], ['status' => 'completed'],
            "Accountant ".auth()->user()->name." completed {$fr->display_ref}: budget utilized P".number_format($actual, 2).".");
        WorkflowService::notifyAdmins("Financial transaction {$fr->display_ref} completed.", "Actual P".number_format($actual, 2)." posted to budget utilization by ".auth()->user()->name.".");
        return redirect()->route('accountant.payables.show', $payable)->with('success', 'Transaction completed. Budget utilization updated with the actual amount.');
    }

    /** Mirror of the admin AP payment-status rules (server-side, no frontend dependency). */
    protected function syncPaymentStatus(AccountsPayable $p): void
    {
        $remaining = (float) $p->remaining_balance;
        $status = 'pending';
        if ($remaining <= 0.009) $status = 'fully_paid';
        elseif ((float) $p->amount_paid > 0) $status = 'partially_paid';
        elseif ($p->due_date && $p->due_date < today()) $status = 'overdue';
        elseif ($p->due_date && $p->due_date->diffInDays(today()) <= 7) $status = 'due_soon';
        $p->update(['payment_status' => $status]);
    }

    // ---------- helpers ----------

    /**
     * Derive every duplicated field from the source — accountant never types these.
     */
    protected function autoFillFromSource(Expense|FinancialRequest $source, string $kind): array
    {
        if ($kind === 'expense') {
            return [
                'vendor' => $source->payee,
                'category' => $source->expense_category,
                'description' => $source->description,
                'budget_plan_id' => $source->budget_plan_id,
                'fund_id' => $source->fund_id,
                'fund_allocation_id' => $source->fund_allocation_id,
                'approved_amount' => (float) $source->amount,
            ];
        }
        $items = $source->metadata['items'] ?? [];
        $suppliers = collect($items)->pluck('supplier')->filter()->unique()->values();
        $categories = collect($items)->pluck('category')->filter()->unique()->values();
        // Resolve budget/allocation through the linked reference when the FR points at one.
        $budgetId = $source->budget_plan_id;
        $allocationId = null;
        if ($source->reference_type === \App\Models\FundAllocation::class && $source->reference_id) {
            $allocationId = $source->reference_id;
            $alloc = \App\Models\FundAllocation::find($allocationId);
            $budgetId = $budgetId ?? $alloc?->budget_plan_id;
        } elseif ($source->reference_type === Expense::class && $source->reference_id) {
            $exp = Expense::find($source->reference_id);
            $budgetId = $budgetId ?? $exp?->budget_plan_id;
            $allocationId = $exp?->fund_allocation_id;
        }
        return [
            'vendor' => $suppliers->isNotEmpty() ? $suppliers->join(', ') : '—',
            'category' => $categories->first() ?? $source->request_type,
            'description' => $source->description,
            'budget_plan_id' => $budgetId,
            'fund_id' => null,
            'fund_allocation_id' => $allocationId,
            'approved_amount' => (float) $source->amount,
        ];
    }

    protected function serializeSource(Expense|FinancialRequest $source, string $kind): array
    {
        $auto = $this->autoFillFromSource($source, $kind);
        $budgetRef = null;
        if ($auto['budget_plan_id'] && ($b = \App\Models\BudgetPlan::find($auto['budget_plan_id']))) {
            $budgetRef = 'BP-2026-'.str_pad((string) $b->id, 4, '0', STR_PAD_LEFT).' — '.$b->budget_name;
        }
        if ($kind === 'expense') {
            return [
                'kind' => $kind, 'id' => $source->id,
                'label' => $source->reference_number.' – '.str($source->description ?? $source->expense_category)->limit(60),
                'vendor' => $auto['vendor'],
                'department' => $source->department,
                'approved_amount' => number_format($auto['approved_amount'], 2),
                'approved_amount_raw' => $auto['approved_amount'],
                'budget_reference' => $budgetRef ?? '—',
                'allocation_reference' => $auto['fund_allocation_id'] ? 'FA-'.$auto['fund_allocation_id'] : '—',
                'description' => $source->description,
            ];
        }
        return [
            'kind' => $kind, 'id' => $source->id,
            'label' => $source->request_number.' – '.str($source->description)->limit(60),
            'vendor' => $auto['vendor'],
            'department' => $source->department,
            'approved_amount' => number_format($auto['approved_amount'], 2),
            'approved_amount_raw' => $auto['approved_amount'],
            'budget_reference' => $budgetRef ?? '—',
            'allocation_reference' => $auto['fund_allocation_id'] ? 'FA-'.$auto['fund_allocation_id'] : '—',
            'description' => $source->description,
        ];
    }

    protected function sourceOptions(?AccountsPayable $current = null): array
    {
        $expenses = Expense::with(['budgetPlan'])
            ->where('approval_status', 'approved')
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($e) => $this->serializeSource($e, 'expense'));
        $requests = FinancialRequest::with('budgetPlan')
            ->whereIn('status', ['approved', 'completed'])
            ->whereIn('request_type', ['expense', 'payable', 'disbursement', 'fund_allocation', 'other'])
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($f) => $this->serializeSource($f, 'financial_request'));

        // Keep the currently linked source selectable even if it aged out of the list.
        if ($current && ($current->expense || $current->financialRequest)) {
            $kind = $current->expense_id ? 'expense' : 'financial_request';
            $src = $current->expense_id ? $current->expense : $current->financialRequest;
            if ($src && ! collect($kind === 'expense' ? $expenses : $requests)->contains('id', $src->id)) {
                $extra = $this->serializeSource($src->load(['budgetPlan']), $kind);
                $kind === 'expense' ? $expenses->prepend($extra) : $requests->prepend($extra);
            }
        }

        return ['expenses' => $expenses, 'requests' => $requests];
    }
}