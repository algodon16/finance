<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
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

    public function create()
    {
        return view('accountant.payables.form', [
            'record' => new AccountsPayable(),
            'sources' => $this->sourceOptions(),
        ]);
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

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate($this->rules());

        $source = $this->resolveSource($data['source_kind'], $data['source_id']);
        $auto = $this->autoFillFromSource($source, $data['source_kind']);

        if (AccountsPayable::where('invoice_number', $data['invoice_number'])
                ->where('vendor', $auto['vendor'])->exists()) {
            return back()->withErrors(['invoice_number' => 'This invoice number already exists for this vendor.'])
                ->withInput();
        }
        abort_if((float) $data['amount'] > (float) $auto['approved_amount'], 422,
            'Payable cannot exceed the approved source amount of ₱'.number_format($auto['approved_amount'], 2).'.');

        $payload = [
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'payment_terms' => $data['payment_terms'],
            'supporting_document' => $request->hasFile('supporting_document')
                ? $request->file('supporting_document')->store('payable-docs', 'public') : null,
            'remarks' => $data['remarks'] ?? null,
            'vendor' => $auto['vendor'],
            'category' => $auto['category'],
            'description' => $auto['description'],
            'budget_plan_id' => $auto['budget_plan_id'],
            'fund_id' => $auto['fund_id'],
            'fund_allocation_id' => $auto['fund_allocation_id'],
            'expense_id' => $data['source_kind'] === 'expense' ? $source->id : null,
            'financial_request_id' => $data['source_kind'] === 'financial_request' ? $source->id : null,
            'amount_paid' => 0,
            'payment_status' => 'pending',
            'approval_status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $record = AccountsPayable::create($payload);
        AuditService::log('create', 'accounts_payable', (string) $record->id, null, null,
            "Accountant ".auth()->user()->name." prepared Payable {$record->invoice_number} from {$data['source_kind']} #{$source->id} (₱".number_format($record->amount, 2).").");

        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'accounts_payable', 'Payable', 'approval_status');
            return redirect()->route('accountant.payables.index')->with('success', 'Payable submitted for admin approval.');
        }
        return redirect()->route('accountant.payables.index')->with('success', 'Payable saved as draft. Unapproved payables cannot become paid transactions.');
    }

    public function show(AccountsPayable $payable)
    {
        $payable->load(['payments', 'budgetPlan', 'fund', 'expense.allocation', 'expense.budgetPlan', 'allocation', 'financialRequest.budgetPlan', 'disbursement']);
        $history = AuditLog::where('module', 'accounts_payable')->where('record_id', (string) $payable->id)->latest('id')->take(20)->get();
        return view('accountant.payables.show', ['record' => $payable, 'history' => $history]);
    }

    public function edit(AccountsPayable $payable)
    {
        abort_if(! in_array($payable->approval_status ?? 'draft', ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Only draft or rejected/revision payables can be edited.');
        $payable->load(['expense', 'financialRequest']);
        return view('accountant.payables.form', [
            'record' => $payable,
            'sources' => $this->sourceOptions($payable),
        ]);
    }

    public function update(Request $request, AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($payable->approval_status ?? 'draft', ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted payables cannot be edited directly.');
        $data = $request->validate($this->rules($payable->id));

        $source = $this->resolveSource($data['source_kind'], $data['source_id']);
        $auto = $this->autoFillFromSource($source, $data['source_kind']);

        if (AccountsPayable::where('invoice_number', $data['invoice_number'])
                ->where('vendor', $auto['vendor'])->where('id', '!=', $payable->id)->exists()) {
            return back()->withErrors(['invoice_number' => 'This invoice number already exists for this vendor.'])
                ->withInput();
        }
        abort_if((float) $data['amount'] > (float) $auto['approved_amount'], 422,
            'Payable cannot exceed the approved source amount of ₱'.number_format($auto['approved_amount'], 2).'.');

        $old = $payable->toArray();
        $payload = [
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'amount' => max((float) $data['amount'], (float) $payable->amount_paid),
            'payment_terms' => $data['payment_terms'],
            'remarks' => $data['remarks'] ?? null,
            'vendor' => $auto['vendor'],
            'category' => $auto['category'],
            'description' => $auto['description'],
            'budget_plan_id' => $auto['budget_plan_id'],
            'fund_id' => $auto['fund_id'],
            'fund_allocation_id' => $auto['fund_allocation_id'],
            'expense_id' => $data['source_kind'] === 'expense' ? $source->id : null,
            'financial_request_id' => $data['source_kind'] === 'financial_request' ? $source->id : null,
            'approval_status' => 'draft',
            'rejection_reason' => null,
            'revision_number' => ((int) $payable->revision_number) + 1,
        ];
        if ($request->hasFile('supporting_document')) {
            $payload['supporting_document'] = $request->file('supporting_document')->store('payable-docs', 'public');
        }
        $payable->update($payload);
        AuditService::log('update', 'accounts_payable', (string) $payable->id, $old, $payable->fresh()->toArray(),
            "Accountant ".auth()->user()->name." revised Payable #{$payable->invoice_number}.");
        return redirect()->route('accountant.payables.show', $payable)->with('success', 'Payable revised and saved as draft.');
    }

    public function submit(AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        WorkflowService::submit($payable, 'accounts_payable', 'Payable', 'approval_status');
        return back()->with('success', 'Payable submitted for admin approval. Payment requires admin authorization.');
    }

    public function cancel(AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($payable->approval_status, ['submitted', 'under_review'], true), 422, 'Only pending submissions can be cancelled.');
        $payable->update(['approval_status' => 'draft', 'submitted_at' => null]);
        AuditService::log('cancel', 'accounts_payable', (string) $payable->id, null, null,
            "Accountant ".auth()->user()->name." cancelled submission of Payable #{$payable->invoice_number}.");
        return back()->with('success', 'Submission cancelled.');
    }

    // ---------- helpers ----------

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'source_kind' => 'required|in:expense,financial_request',
            'source_id' => 'required|integer|min:1',
            'invoice_number' => ['required', 'string', 'max:100',
                $ignoreId
                    ? Rule::unique('accounts_payable', 'invoice_number')->ignore($ignoreId)
                    : 'unique:accounts_payable,invoice_number'],
            'invoice_date' => 'required|date|before_or_equal:today',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'payment_terms' => 'required|in:COD,15 Days,30 Days,60 Days,Custom',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ];
    }

    protected function resolveSource(string $kind, int $id): Expense|FinancialRequest
    {
        if ($kind === 'expense') {
            $source = Expense::with(['budgetPlan', 'allocation'])->findOrFail($id);
            abort_if($source->approval_status !== 'approved', 422, 'Payable must link an approved expense proposal.');
            return $source;
        }
        $source = FinancialRequest::with('budgetPlan')->findOrFail($id);
        abort_if(! in_array($source->status, ['approved', 'completed'], true), 422, 'Payable must link an approved financial request.');
        return $source;
    }

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
