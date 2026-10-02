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
        // Legacy entry point now lands on the hub Overview.
        return redirect()->route('accountant.budgets.overview');
    }

    /** Persist budget breakdown items (replaces previous draft items). */
    protected function syncItems(BudgetPlan $budget, array $lines): void
    {
        $budget->items()->delete();
        foreach ($lines as $line) {
            $budget->items()->create([
                'item_name' => $line['description'], 'category' => $line['category'],
                'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'], 'line_total' => $line['line_total'],
                'created_by' => auth()->id(),
            ]);
        }
    }

    /** Validate breakdown items; on submit, total must equal requested. Returns computed lines. */
    protected function validateItems(Request $request, float $requested, bool $isSubmit, string $type): array
    {
        $isQty = in_array($type, BudgetPlan::QTY_TYPES, true);
        $items = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.category' => 'required|string|max:255',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => ($isQty ? 'required' : 'nullable').'|integer|min:1|max:1000000',
            'items.*.unit_cost' => ($isQty ? 'required' : 'nullable').'|numeric|min:0|max:999999999999.99',
            'items.*.amount' => ($isQty ? 'nullable' : 'required').'|numeric|min:0.01|max:999999999999.99',
        ])['items'];
        $lines = [];
        foreach ($items as $it) {
            $qty = $isQty ? (int) $it['quantity'] : 1;
            $unit = $isQty ? (float) $it['unit_cost'] : (float) $it['amount'];
            $lineTotal = number_format($qty * $unit, 2, '.', '');
            $lines[] = ['category' => $it['category'], 'description' => $it['description'],
                'quantity' => $qty, 'unit_cost' => number_format($unit, 2, '.', ''), 'line_total' => $lineTotal];
        }
        if ($isSubmit) {
            $total = array_sum(array_map(fn($l) => (float) $l['line_total'], $lines));
            if (abs($total - $requested) > 0.009) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Budget breakdown total (P'.number_format($total, 2).') must equal the Requested Budget Amount (P'.number_format($requested, 2).').',
                ]);
            }
        }
        // Validate free-form type details (all optional).
        $request->validate([
            'request_details' => 'nullable|array',
            'request_details.overtime_amount' => 'nullable|numeric|min:0',
            'request_details.participants' => 'nullable|integer|min:0',
            'request_details.transfer_amount' => 'nullable|numeric|min:0',
        ]);
        return $lines;
    }

    /** Hub filter options shared by requests/planning pages. */
    protected function hubFilters(): array
    {
        $yearCol = BudgetPlan::yearColumn();
        try {
            $years = BudgetPlan::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }
        return [
            'years' => $years,
            'departments' => BudgetPlan::whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department'),
            'categories' => BudgetPlan::whereNotNull('budget_category')->where('budget_category', '<>', '')->distinct()->orderBy('budget_category')->pluck('budget_category'),
        ];
    }

    protected function requestCounts(): array
    {
        return [
            'total' => (int) BudgetPlan::count(),
            'pending' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_PENDING)->count(),
            'review' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_REVIEW)->count(),
            'approval' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_AWAITING_ADMIN)->count(),
            'approved' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_APPROVED)->count(),
            'rejected' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_REJECTED)->count(),
            'returned' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_RETURNED)->count(),
        ];
    }

    protected function applyRequestFilters($q, Request $request)
    {
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")
                ->orWhere('department', 'ilike', "%{$s}%")
                ->orWhere('budget_category', 'ilike', "%{$s}%")
                ->orWhere('funding_source', 'ilike', "%{$s}%"));
            if (ctype_digit($s)) $q->orWhere('id', (int) $s);
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        $yearCol = BudgetPlan::yearColumn();
        if ($request->filled('academic_year')) $q->where($yearCol, $request->academic_year);
        if ($request->filled('department')) $q->where('department', $request->department);
        if ($request->filled('budget_category')) $q->where('budget_category', $request->budget_category);
        return $q;
    }

    /** Workspace — single integrated lifecycle view (request → … → history). */
    public function overview(Request $request)
    {
        $data = BudgetPlan::workspace($request->input('search'), $request->input('status'), $request->input('academic_year'));
        $fundMap = Fund::where('status', 'active')->get()->keyBy('fund_name');
        return view('accountant.budgets.overview', array_merge($data, compact('fundMap')));
    }

    /** 2. Budget Requests — card-based review interface. */
    public function requests(Request $request)
    {
        $counts = $this->requestCounts();
        $q = $this->applyRequestFilters(BudgetPlan::with(['creator', 'submitter'])->orderByDesc('created_at'), $request);
        $plans = $q->paginate(12)->withQueryString();
        // Fund lookup map for availability panels (no N+1).
        $fundMap = Fund::where('status', 'active')->get()->keyBy('fund_name');
        return view('accountant.budgets.requests', array_merge(
            $this->hubFilters(),
            compact('counts', 'plans', 'fundMap')
        ));
    }

    /** 3. Budget Planning — classic planning table (existing workflow untouched). */
    public function planning(Request $request)
    {
        $q = BudgetPlan::with(['creator', 'approver'])->orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%")->orWhere('budget_category', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        $yearCol = BudgetPlan::yearColumn();
        if ($request->filled('academic_year')) $q->where($yearCol, $request->academic_year);
        $plans = $q->paginate(12)->withQueryString();
        try {
            $years = BudgetPlan::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }
        $summary = [
            'draft' => (int) BudgetPlan::where('status', 'draft')->count(),
            'pending' => (int) BudgetPlan::whereIn('status', ['submitted', 'under_review', 'for_approval'])->count(),
            'approved' => (int) BudgetPlan::whereIn('status', ['approved', 'active'])->count(),
            'revision' => (int) BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->count(),
            'total' => (float) BudgetPlan::sum('allocated_amount'),
        ];

        return view('accountant.budgets.planning', compact('plans', 'summary', 'years'));
    }

    /** 6. Budget History — complete per-request record (no hard deletes; cancelled = archived). */
    public function history(Request $request)
    {
        $q = BudgetPlan::with(['creator', 'reviewer', 'approver'])->orderByDesc('created_at');
        $q = $this->applyRequestFilters($q, $request);
        $plans = $q->paginate(15)->withQueryString();
        return view('accountant.budgets.history', array_merge($this->hubFilters(), compact('plans')));
    }

    /**
     * Accountant review: accept/adjust the requested amount into a proposed
     * amount and forward the request For Admin Approval.
     */
    public function review(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be reviewed.');
        $data = $request->validate([
            'proposed_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_recommendation' => 'nullable|in:full,partial,no_allocation',
            'documents_verified' => 'nullable|array',
            'documents_verified.*' => 'in:verified',
            'accountant_remarks' => 'nullable|string|max:5000',
        ]);
        $requested = $budget->requested_amount_value;
        $proposed = (float) $data['proposed_amount'];
        // Remarks required when the proposal is lower than the request.
        if ($proposed < $requested && empty(trim((string) ($data['accountant_remarks'] ?? '')))) {
            return back()->withErrors(['accountant_remarks' => 'Review remarks are required when the proposed budget is lower than the requested budget.'])->withInput();
        }
        // Guard: never propose beyond the linked fund's availability.
        if ($budget->funding_source && ($fund = Fund::where('fund_name', $budget->funding_source)->where('status', 'active')->first())) {
            $available = (float) $fund->available_amount;
            if ((float) $data['proposed_amount'] > $available) {
                return back()->withErrors(['proposed_amount' => 'Insufficient available fund for this allocation. Available: P'.number_format($available, 2).'.'])->withInput();
            }
        }
        $old = ['status' => $budget->status, 'requested_amount' => $budget->requested_amount_value, 'proposed_amount' => $budget->proposed_amount];
        DB::transaction(function () use ($budget, $data) {
            $budget->update([
                'proposed_amount' => $data['proposed_amount'],
                'allocation_recommendation' => $data['allocation_recommendation'] ?? $budget->allocation_recommendation,
                'documents_verified' => $data['documents_verified'] ?? $budget->documents_verified,
                'accountant_remarks' => $data['accountant_remarks'] ?? $budget->accountant_remarks,
                'status' => 'for_approval',
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
        });
        AuditService::log('review', 'budget_plans', (string) $budget->id, $old,
            ['status' => 'for_approval', 'proposed_amount' => (float) $data['proposed_amount'], 'allocation_recommendation' => $data['allocation_recommendation'] ?? null, 'documents_verified' => $data['documents_verified'] ?? []],
            "Accountant ".auth()->user()->name." reviewed {$budget->request_id} (requested P".number_format($budget->requested_amount_value, 2)." → proposed P".number_format((float) $data['proposed_amount'], 2).", ".(BudgetPlan::RECOMMENDATIONS[$data['allocation_recommendation'] ?? ''] ?? 'no recommendation')."). Forwarded for admin approval.");
        WorkflowService::notifyAdmins("Budget request {$budget->request_id} forwarded for approval.", "Accountant proposed P".number_format((float) $data['proposed_amount'], 2)." for {$budget->department}. Review it under Budget Requests.");
        return back()->with('success', 'Proposed budget saved. Request forwarded for admin approval.');
    }

    /**
     * Accountant returns a submitted request for revision (remarks required).
     * Status-based handling — the record is preserved, never deleted.
     */
    public function returnForRevision(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be returned.');
        $data = $request->validate(['accountant_remarks' => 'required|string|max:5000']);
        $old = $budget->status;
        DB::transaction(fn() => $budget->update([
            'status' => 'for_revision',
            'accountant_remarks' => $data['accountant_remarks'],
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('return', 'budget_plans', (string) $budget->id, ['status' => $old], ['status' => 'for_revision'],
            "Accountant ".auth()->user()->name." returned {$budget->request_id} for revision: {$data['accountant_remarks']}");
        return back()->with('success', 'Request returned for revision.');
    }

    /** Master data for budget forms (departments are data, never users). */
    protected function formMasterData(): array
    {
        return [
            'departments' => \App\Models\Department::active()->orderBy('name')->pluck('name'),
            'academicYears' => \App\Models\AcademicYear::where('is_active', true)->orderBy('name')->pluck('name'),
        ];
    }

    public function create()
    {
        $funds = Fund::where('status', 'active')->orderBy('fund_name')->get();
        $availableFunds = (float) $funds->sum(fn($f) => (float) $f->available_amount);
        return view('accountant.budgets.form', array_merge(
            ['plan' => new BudgetPlan(), 'funds' => $funds, 'availableFunds' => $availableFunds],
            $this->formMasterData()
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:20|exists:academic_years,name',
            'department' => 'required|string|max:255|exists:departments,name',
            'request_type' => 'required|string|in:'.implode(',', array_keys(BudgetPlan::REQUEST_TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'justification' => 'required|string|max:5000',
            'funding_source' => 'required|string|max:255|exists:funds,fund_name',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'requested_amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        // Requested Budget Amount: real editable currency input, must be > 0.
        $total = number_format((float) $data['requested_amount'], 2, '.', '');
        abort_if((float) $total <= 0, 422, 'Requested budget amount must be greater than 0.');
        // Fund guard: requested must not exceed the selected fund's availability.
        $fund = Fund::where('fund_name', $data['funding_source'])->where('status', 'active')->first();
        abort_if(! $fund, 422, 'Selected fund source is not an active fund.');
        abort_if((float) $total > (float) $fund->available_amount, 422, 'Requested amount exceeds available fund of P'.number_format((float) $fund->available_amount, 2).'.');
        $isSubmit = $request->input('action') === 'submit';
        $lines = $this->validateItems($request, (float) $total, $isSubmit, $data['request_type']);
        $details = collect($request->input('request_details', []))->filter(fn($v) => $v !== null && $v !== '')->all();
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('budget-docs', 'public');
        }
        $plan = DB::transaction(function () use ($data, $total, $request, $details) {
            $plan = BudgetPlan::create([
                'budget_name' => $data['budget_name'], 'academic_year' => $data['academic_year'],
                'department' => $data['department'],
                'budget_category' => BudgetPlan::REQUEST_TYPES[$data['request_type']],
                'request_type' => $data['request_type'], 'request_details' => $details ?: null,
                'allocated_amount' => $total, 'utilized_amount' => 0,
                'requested_amount' => $total, 'proposed_amount' => null,
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                'status' => 'draft', 'description' => ($data['description'] ?? '').(($data['notes'] ?? '') ? "\nNotes: ".$data['notes'] : ''),
                'justification' => $data['justification'] ?? null, 'funding_source' => $data['funding_source'],
                'supporting_document' => $data['supporting_document'] ?? null,
                'created_by' => auth()->id(),
            ]);
            return $plan;
        });
        $this->syncItems($plan, $lines);
        AuditService::log('create', 'budget_plans', (string) $plan->id, null, null, "Accountant ".auth()->user()->name." prepared Budget Request {$plan->request_id} ({$plan->budget_name}) requested P".number_format($total, 2)." with ".count($lines)." breakdown items.");

        if ($isSubmit) {
            WorkflowService::submit($plan->fresh(), 'budget_plans', 'Budget Request');
            return redirect()->route('accountant.budgets.index')->with('success', 'Budget request submitted for review.');
        }
        return redirect()->route('accountant.budgets.index')->with('success', 'Budget request saved as draft.');
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
        return view('accountant.budgets.form', array_merge(
            ['plan' => $budget, 'funds' => $funds, 'availableFunds' => $availableFunds],
            $this->formMasterData()
        ));
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($budget->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted budgets cannot be edited directly. Revise after rejection.');
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:20|exists:academic_years,name',
            'department' => 'required|string|max:255|exists:departments,name',
            'request_type' => 'required|string|in:'.implode(',', array_keys(BudgetPlan::REQUEST_TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'justification' => 'required|string|max:5000',
            'funding_source' => 'required|string|max:255|exists:funds,fund_name',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'requested_amount' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        $total = number_format((float) $data['requested_amount'], 2, '.', '');
        $lines = $this->validateItems($request, (float) $total, false, $data['request_type']);
        $details = collect($request->input('request_details', []))->filter(fn($v) => $v !== null && $v !== '')->all();
        $fund = Fund::where('fund_name', $data['funding_source'])->where('status', 'active')->first();
        abort_if(! $fund, 422, 'Selected fund source is not an active fund.');
        abort_if((float) $total > (float) $fund->available_amount, 422, 'Requested amount exceeds available fund of P'.number_format((float) $fund->available_amount, 2).'.');
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('budget-docs', 'public');
        }
        $old = $budget->toArray();
        DB::transaction(function () use ($budget, $data, $total, $details) {
            $budget->update([
                'budget_name' => $data['budget_name'], 'academic_year' => $data['academic_year'],
                'department' => $data['department'],
                'budget_category' => BudgetPlan::REQUEST_TYPES[$data['request_type']],
                'request_type' => $data['request_type'], 'request_details' => $details ?: null,
                'allocated_amount' => $total,
                'requested_amount' => $total, 'proposed_amount' => null,
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
                'status' => 'draft', 'description' => ($data['description'] ?? '').(($data['notes'] ?? '') ? "\nNotes: ".$data['notes'] : ''),
                'justification' => $data['justification'] ?? null, 'funding_source' => $data['funding_source'],
                'supporting_document' => $data['supporting_document'] ?? $budget->supporting_document,
                'rejection_reason' => null,
                'revision_number' => ((int) $budget->revision_number) + 1,
            ]);
        });
        $this->syncItems($budget->fresh(), $lines);
        AuditService::log('update', 'budget_plans', (string) $budget->id, $old, $budget->fresh()->toArray(), "Accountant ".auth()->user()->name." revised Budget Plan #{$budget->id} (rev ".((int) $budget->revision_number + 1)."). Original history preserved.");
        return redirect()->route('accountant.budgets.show', $budget)->with('success', 'Budget plan revised and saved as draft. Submit for admin approval when ready.');
    }

    public function submit(BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'accountant', 403, 'Accountant cannot approve own submission.');
        abort_if((float) $budget->requested_amount_value <= 0, 422, 'Enter a requested budget amount greater than 0 before submitting.');
        // Breakdown integrity: items must exist and total exactly the requested amount.
        $itemTotal = (float) $budget->items()->sum('line_total');
        abort_if($budget->items()->count() < 1, 422, 'Add at least one budget breakdown item before submitting.');
        abort_if(abs($itemTotal - (float) $budget->requested_amount_value) > 0.009, 422, 'Budget breakdown total (P'.number_format($itemTotal, 2).') must equal the Requested Budget Amount (P'.number_format($budget->requested_amount_value, 2).').');
        // One official budget per department + year — drafts stay exempt, submission is the gate.
        if ($dup = BudgetPlan::officialDuplicateExists($budget->department, $budget->academic_year, $budget->id)) {
            return back()->withErrors(['department' => 'Department "'.$budget->department.'" already has an official budget for '.$budget->academic_year.' (BUD-'.$dup->id.' — '.$dup->status.'). Revise that record instead of submitting a duplicate.']);
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
