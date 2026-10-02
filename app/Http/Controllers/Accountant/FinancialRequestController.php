<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Department;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\FundAllocation;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class FinancialRequestController extends Controller
{
    public function index(Request $request)
    {
        $q = FinancialRequest::with(['preparer', 'decider', 'student', 'budgetPlan'])->orderByDesc('created_at');
        if ($request->filled('request_type')) $q->where('request_type', $request->request_type);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('source_system')) $q->where('source_system', $request->source_system);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('request_number', 'ilike', "%{$s}%")
                ->orWhere('source_request_id', 'ilike', "%{$s}%")
                ->orWhere('description', 'ilike', "%{$s}%")
                ->orWhere('department', 'ilike', "%{$s}%"));
        }
        $records = $q->paginate(12)->withQueryString();
        // Per-row budget availability (uses the same committed math as validation).
        $validMap = [];
        foreach ($records as $r) {
            $validMap[$r->id] = $r->budget_plan_id
                ? FinancialRequest::budgetValidation($r->budgetPlan, (float) $r->amount, $r->id)
                : null;
        }
        $summary = [
            'incoming' => (int) FinancialRequest::where('status', 'submitted')->count(),
            'review' => (int) FinancialRequest::where('status', 'under_review')->count(),
            'approved' => (int) FinancialRequest::where('status', 'approved')->count(),
            'completed' => (int) FinancialRequest::where('status', 'completed')->count(),
            'returned' => (int) FinancialRequest::whereIn('status', ['rejected', 'for_revision', 'revision'])->count(),
            'impact' => (float) FinancialRequest::whereIn('status', ['approved', 'completed'])->sum('amount'),
        ];
        $departments = Department::active()->orderBy('name')->pluck('name');
        $budgets = BudgetPlan::whereIn('status', ['approved', 'active'])->orderByDesc('id')->limit(200)->get();
        return view('accountant.financial-requests.index', compact('records', 'summary', 'validMap', 'departments', 'budgets'));
    }

    public function create()
    {
        return view('accountant.financial-requests.form', [
            'record' => new FinancialRequest(),
            'linkKind' => 'none',
            'linkId' => null,
            'students' => Student::orderBy('first_name')->limit(200)->get(),
            'budgets' => BudgetPlan::orderByDesc('id')->limit(200)->get(),
            'allocations' => FundAllocation::orderByDesc('id')->limit(200)->get(),
            'expenses' => Expense::orderByDesc('id')->limit(200)->get(),
            'payables' => AccountsPayable::orderByDesc('id')->limit(200)->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'catalogItems' => SupplierItem::with('supplier')->active()->orderBy('item_name')->limit(200)->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'request_type' => 'required|in:'.implode(',', FinancialRequest::TYPES),
            'description' => 'required|string|max:5000',
            'department' => 'required|string|max:255',
            'request_date' => 'nullable|date',
            'student_id' => 'nullable|exists:students,id',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'reference_kind' => 'nullable|in:budget,allocation,expense,payable,none',
            'reference_id' => 'nullable|integer|min:1',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.category' => 'nullable|string|max:255',
            'items.*.supplier' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0.01|max:999999999999.99',
            'source_system' => 'nullable|string|max:50|in:'.implode(',', array_keys(FinancialRequest::SOURCES)),
            'source_request_id' => 'nullable|string|max:100',
        ]);
        // External source identity must be unique (never import twice).
        if (! empty($data['source_request_id'])) {
            $dup = FinancialRequest::where('source_system', $data['source_system'] ?? 'internal')
                ->where('source_request_id', $data['source_request_id'])->exists();
            abort_if($dup, 422, 'This source request was already received ('.($data['source_system'] ?? 'internal').' '.$data['source_request_id'].').');
        }
        $map = ['budget' => BudgetPlan::class, 'allocation' => FundAllocation::class, 'expense' => Expense::class, 'payable' => AccountsPayable::class];
        $refType = null; $refId = null;
        if (! empty($data['reference_kind']) && $data['reference_kind'] !== 'none' && ! empty($data['reference_id'])) {
            $cls = $map[$data['reference_kind']];
            $linked = $cls::findOrFail($data['reference_id']);
            $refType = $cls; $refId = $linked->id;
        }
        // Kung may piniling Budget ID, i-validate na existing ito.
        // Kung walang Budget ID pero naka-link sa Budget Plan, gamitin ang link bilang Budget ID.
        $budgetPlanId = $data['budget_plan_id'] ?? null;
        if (empty($budgetPlanId) && ($data['reference_kind'] ?? 'none') === 'budget' && ! empty($refId)) {
            $budgetPlanId = $refId;
        }
        // Default sa araw na ito kung walang date na nilagay.
        if (empty($data['request_date'])) {
            $data['request_date'] = now()->toDateString();
        }
        // Server-side total from budget items — never trust a header amount.
        $total = '0';
        $cleanItems = [];
        foreach ($data['items'] as $it) {
            $line = bcmul((string) $it['quantity'], (string) $it['unit_cost'], 2);
            $total = bcadd($total, $line, 2);
            $cleanItems[] = [
                'item_name' => $it['item_name'],
                'category' => $it['category'] ?? null,
                'supplier' => $it['supplier'] ?? null,
                'quantity' => (int) $it['quantity'],
                'unit_cost' => number_format((float) $it['unit_cost'], 2, '.', ''),
                'line_total' => $line,
            ];
        }
        abort_if((float) $total <= 0, 422, 'Total amount must be greater than 0.');
        // I-save ang department at suppliers sa sariling database para magamit ulit sa susunod.
        if (! empty($data['department']) && ($dept = Department::findOrAdd($data['department']))) {
            $data['department'] = $dept->name;
        }
        foreach ($cleanItems as $idx => $it) {
            if (! empty($it['supplier']) && ($sup = Supplier::findOrAdd($it['supplier']))) {
                $cleanItems[$idx]['supplier'] = $sup->name;
            }
        }
        // I-record ang bawat item sa catalog ng supplier para mahanap balang-araw.
        foreach ($cleanItems as $it) {
            SupplierItem::record($it['supplier'] ?? '', $it['item_name'], $it['category'] ?? null, $it['unit_cost'] ?? null);
        }
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('financial-requests', 'public');
        }
        $data['request_number'] = 'FR-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        $data['status'] = 'draft';
        $data['prepared_by'] = auth()->id();
        $data['received_at'] = now();
        $data['reference_type'] = $refType;
        $data['reference_id'] = $refId;
        $data['budget_plan_id'] = $budgetPlanId;
        $data['amount'] = $total;
        $data['metadata'] = ['items' => $cleanItems];
        unset($data['reference_kind'], $data['items']);
        $record = FinancialRequest::create($data);
        AuditService::log('create', 'financial_requests', (string) $record->id, null, null, "Accountant ".auth()->user()->name." prepared Financial Request {$record->request_number} ({$record->request_type}).");
        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'financial_requests', 'Financial Request');
            return redirect()->route('accountant.financial-requests.index')->with('success', 'Financial request submitted for admin approval.');
        }
        return redirect()->route('accountant.financial-requests.index')->with('success', 'Financial request saved as draft.');
    }

    public function show(FinancialRequest $financialRequest)
    {
        $financialRequest->load(['preparer', 'decider', 'student', 'budgetPlan', 'payable.disbursement', 'payable.payments']);
        $linked = null;
        if ($financialRequest->reference_type && $financialRequest->reference_id && class_exists($financialRequest->reference_type)) {
            try { $linked = $financialRequest->reference_type::find($financialRequest->reference_id); } catch (\Throwable $e) {}
        }
        $history = AuditLog::where('module', 'financial_requests')->where('record_id', (string) $financialRequest->id)->latest('id')->take(20)->get();
        $validation = $financialRequest->budget_plan_id
            ? FinancialRequest::budgetValidation($financialRequest->budgetPlan, (float) $financialRequest->amount, $financialRequest->id)
            : null;
        return view('accountant.financial-requests.show', ['record' => $financialRequest, 'linked' => $linked, 'history' => $history, 'validation' => $validation]);
    }

    public function edit(FinancialRequest $financialRequest)
    {
        abort_if(! $financialRequest->isEditable(), 422, 'Only draft or for-revision requests can be edited.');
        // External records are owned by the source subsystem — never rewritten here.
        // Corrections go back through return/withdraw, not direct edits.
        abort_if(($financialRequest->source_system ?? 'internal') !== 'internal', 422, 'Received requests cannot be edited here. Return it to the source subsystem instead.');
        $kindMap = [BudgetPlan::class => 'budget', FundAllocation::class => 'allocation', Expense::class => 'expense', AccountsPayable::class => 'payable'];
        return view('accountant.financial-requests.form', [
            'record' => $financialRequest,
            'linkKind' => $kindMap[$financialRequest->reference_type] ?? 'none',
            'linkId' => $financialRequest->reference_id,
            'students' => Student::orderBy('first_name')->limit(200)->get(),
            'budgets' => BudgetPlan::orderByDesc('id')->limit(200)->get(),
            'allocations' => FundAllocation::orderByDesc('id')->limit(200)->get(),
            'expenses' => Expense::orderByDesc('id')->limit(200)->get(),
            'payables' => AccountsPayable::orderByDesc('id')->limit(200)->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'catalogItems' => SupplierItem::with('supplier')->active()->orderBy('item_name')->limit(200)->get(),
        ]);
    }

    public function update(Request $request, FinancialRequest $financialRequest)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! $financialRequest->isEditable(), 422, 'Approved/submitted requests cannot be edited directly.');
        abort_if(($financialRequest->source_system ?? 'internal') !== 'internal', 422, 'Received requests cannot be edited here. Return it to the source subsystem instead.');
        $data = $request->validate([
            'request_type' => 'required|in:'.implode(',', FinancialRequest::TYPES),
            'description' => 'required|string|max:5000',
            'department' => 'required|string|max:255',
            'request_date' => 'nullable|date',
            'student_id' => 'nullable|exists:students,id',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'reference_kind' => 'nullable|in:budget,allocation,expense,payable,none',
            'reference_id' => 'nullable|integer|min:1',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.category' => 'nullable|string|max:255',
            'items.*.supplier' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0.01|max:999999999999.99',
        ]);
        $map = ['budget' => BudgetPlan::class, 'allocation' => FundAllocation::class, 'expense' => Expense::class, 'payable' => AccountsPayable::class];
        $refType = $financialRequest->reference_type; $refId = $financialRequest->reference_id;
        if (array_key_exists('reference_kind', $data)) {
            if (! empty($data['reference_kind']) && $data['reference_kind'] !== 'none' && ! empty($data['reference_id'])) {
                $cls = $map[$data['reference_kind']];
                $linked = $cls::findOrFail($data['reference_id']);
                $refType = $cls; $refId = $linked->id;
            } else {
                $refType = null; $refId = null;
            }
        }
        $budgetPlanId = $data['budget_plan_id'] ?? null;
        if (empty($budgetPlanId) && ($data['reference_kind'] ?? null) === 'budget' && ! empty($refId)) {
            $budgetPlanId = $refId;
        }
        $total = '0';
        $cleanItems = [];
        foreach ($data['items'] as $it) {
            $line = bcmul((string) $it['quantity'], (string) $it['unit_cost'], 2);
            $total = bcadd($total, $line, 2);
            $cleanItems[] = [
                'item_name' => $it['item_name'],
                'category' => $it['category'] ?? null,
                'supplier' => $it['supplier'] ?? null,
                'quantity' => (int) $it['quantity'],
                'unit_cost' => number_format((float) $it['unit_cost'], 2, '.', ''),
                'line_total' => $line,
            ];
        }
        abort_if((float) $total <= 0, 422, 'Total amount must be greater than 0.');
        // I-save ang department at suppliers sa sariling database para magamit ulit sa susunod.
        if (! empty($data['department']) && ($dept = Department::findOrAdd($data['department']))) {
            $data['department'] = $dept->name;
        }
        foreach ($cleanItems as $idx => $it) {
            if (! empty($it['supplier']) && ($sup = Supplier::findOrAdd($it['supplier']))) {
                $cleanItems[$idx]['supplier'] = $sup->name;
            }
        }
        // I-record ang bawat item sa catalog ng supplier para mahanap balang-araw.
        foreach ($cleanItems as $it) {
            SupplierItem::record($it['supplier'] ?? '', $it['item_name'], $it['category'] ?? null, $it['unit_cost'] ?? null);
        }
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('financial-requests', 'public');
        }
        $old = $financialRequest->toArray();
        $data['status'] = 'draft';
        $data['admin_decision'] = null;
        $data['admin_remarks'] = null;
        $data['amount'] = $total;
        $data['reference_type'] = $refType;
        $data['reference_id'] = $refId;
        $data['budget_plan_id'] = $budgetPlanId;
        $meta = is_array($financialRequest->metadata) ? $financialRequest->metadata : [];
        $meta['items'] = $cleanItems;
        $data['metadata'] = $meta;
        unset($data['items'], $data['reference_kind']);
        $financialRequest->update($data);
        AuditService::log('update', 'financial_requests', (string) $financialRequest->id, $old, $financialRequest->fresh()->toArray(), "Accountant ".auth()->user()->name." revised Financial Request {$financialRequest->request_number}.");
        return redirect()->route('accountant.financial-requests.show', $financialRequest)->with('success', 'Request revised and saved as draft.');
    }

    public function submit(FinancialRequest $financialRequest)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if((float) $financialRequest->amount <= 0, 422, 'Add at least one budget item with amount greater than 0 before submitting.');
        // Budget validation gate: linked budget must cover the request.
        if ($financialRequest->budget_plan_id) {
            $v = FinancialRequest::budgetValidation($financialRequest->budgetPlan, (float) $financialRequest->amount, $financialRequest->id);
            abort_if(! $v, 422, 'Linked budget is not approved/active.');
            abort_if(! $v['valid'], 422, 'Budget insufficient: requested P'.number_format((float) $financialRequest->amount, 2).' exceeds available P'.number_format($v['available'], 2).' (BR-'.$financialRequest->budget_plan_id.').');
        }
        WorkflowService::submit($financialRequest, 'financial_requests', 'Financial Request');
        $financialRequest->update(['reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
        return back()->with('success', 'Financial request submitted for admin approval.');
    }

    public function cancel(FinancialRequest $financialRequest)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! $financialRequest->isPending(), 422, 'Only pending submissions can be cancelled.');
        $financialRequest->update(['status' => 'draft', 'submitted_at' => null]);
        AuditService::log('cancel', 'financial_requests', (string) $financialRequest->id, null, null, "Accountant ".auth()->user()->name." cancelled submission of Financial Request {$financialRequest->request_number}.");
        return back()->with('success', 'Submission cancelled.');
    }

    /**
     * Accountant review: save recommended amount + financial remarks, then
     * either forward to admin or return to the source subsystem.
     * The original request content is never rewritten — only review metadata.
     */
    public function review(Request $request, FinancialRequest $financialRequest)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($financialRequest->status, ['submitted', 'under_review'], true), 422, 'Only received requests can be reviewed.');
        $data = $request->validate([
            'recommended_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'financial_remarks' => 'nullable|string|max:60',
            'decision' => 'required|in:forward,return',
        ]);
        if ($data['decision'] === 'return' && empty(trim((string) ($data['financial_remarks'] ?? '')))) {
            return back()->withErrors(['financial_remarks' => 'Remarks are required when returning to the source.'])->withInput();
        }
        // Budget gate applies to the recommended amount as well.
        if ($financialRequest->budget_plan_id) {
            $v = FinancialRequest::budgetValidation($financialRequest->budgetPlan, (float) $data['recommended_amount'], $financialRequest->id);
            abort_if(! $v, 422, 'Linked budget is not approved/active.');
            abort_if(! $v['valid'], 422, 'Budget insufficient: recommended P'.number_format((float) $data['recommended_amount'], 2).' exceeds available P'.number_format($v['available'], 2).'.');
        }
        $meta = $financialRequest->metadata ?? [];
        $meta['recommended_amount'] = (float) $data['recommended_amount'];
        $meta['financial_remarks'] = $data['financial_remarks'] ?? null;
        $old = $financialRequest->status;
        if ($data['decision'] === 'return') {
            $financialRequest->update([
                'status' => 'for_revision', 'metadata' => $meta,
                'admin_remarks' => $data['financial_remarks'],
                'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
                'revision_number' => ((int) $financialRequest->revision_number) + 1,
            ]);
            AuditService::log('return', 'financial_requests', (string) $financialRequest->id, ['status' => $old], ['status' => 'for_revision'],
                "Accountant ".auth()->user()->name." returned {$financialRequest->display_ref} to source: {$data['financial_remarks']}");
            return back()->with('success', 'Request returned to source subsystem.');
        }
        $financialRequest->update([
            'status' => 'under_review', 'metadata' => $meta,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
        ]);
        AuditService::log('review', 'financial_requests', (string) $financialRequest->id, ['status' => $old],
            ['status' => 'under_review', 'recommended_amount' => (float) $data['recommended_amount']],
            "Accountant ".auth()->user()->name." reviewed {$financialRequest->display_ref}: recommended P".number_format((float) $data['recommended_amount'], 2).". Forwarded to admin.");
        WorkflowService::notifyAdmins("Financial request {$financialRequest->display_ref} forwarded for approval.", "Recommended P".number_format((float) $data['recommended_amount'], 2).".");
        return back()->with('success', 'Review saved. Request forwarded to admin.');
    }

    /** Demo intake: simulate a subsystem request through the real validation path. */
    public function simulate(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'source_system' => 'required|string|max:50|in:'.implode(',', array_keys(array_filter(FinancialRequest::SOURCES, fn($k) => $k !== 'internal', ARRAY_FILTER_USE_KEY))),
            'request_type' => 'required|string|max:50',
            'department' => 'required|string|max:255|exists:departments,name',
            'source_request_id' => 'nullable|string|max:100',
            'purpose' => 'required|string|max:5000',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'budget_plan_id' => 'required|exists:budget_plans,id',
        ]);
        $record = FinancialRequest::receiveSimulated($data, auth()->id());
        return redirect()->route('accountant.financial-requests.show', $record)->with('success', 'Demo request received as '.$record->display_ref.'.');
    }

    /** Procurement tab of Procurement and Financial Requests — student procurement requests (read-only). */
    public function procurement(Request $request)
    {
        $q = \App\Models\ProcurementRequest::with(['student', 'reviewer'])->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('request_number', 'ilike', "%{$s}%")->orWhere('item_description', 'ilike', "%{$s}%"));
        }
        $records = $q->paginate(15)->withQueryString();
        return view('accountant.financial-requests.procurement', compact('records'));
    }

    /**
     * JSON preview of the linked record's budget.
     * Used by the Prepare form: pag nag-input ng Record ID, automatic na
     * lumalabas kung magkano ang budget at kung magkano ang matitira.
     * GET /accountant/financial-requests/link-preview?kind=budget&id=3
     */
    public function linkPreview(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $map = ['budget' => BudgetPlan::class, 'allocation' => FundAllocation::class, 'expense' => Expense::class, 'payable' => AccountsPayable::class];
        $kind = (string) $request->query('kind', '');
        $id = (int) $request->query('id', 0);
        if (! isset($map[$kind]) || $id < 1) {
            return response()->json(['found' => false]);
        }
        $rec = $map[$kind]::find($id);
        if (! $rec) {
            return response()->json(['found' => false]);
        }

        if ($kind === 'budget') {
            return response()->json([
                'found' => true,
                'kind' => $kind,
                'id' => $rec->id,
                'label' => "#{$rec->id} {$rec->budget_name} ({$rec->academic_year})",
                'department' => $rec->department,
                'status' => $rec->status,
                'allocated' => (float) $rec->allocated_amount,
                'used' => (float) $rec->utilized_amount,
                'available' => (float) $rec->remaining_amount,
            ]);
        }

        if ($kind === 'allocation') {
            $rec->load(['fund', 'budgetPlan']);
            $fundAvail = $rec->fund
                ? (float) bcsub((string) $rec->fund->current_balance, (string) $rec->fund->reserved_amount, 2)
                : null;
            return response()->json([
                'found' => true,
                'kind' => $kind,
                'id' => $rec->id,
                'label' => "#{$rec->id} {$rec->purpose} → {$rec->allocated_to}",
                'department' => $rec->allocated_to,
                'status' => $rec->status,
                'allocated' => (float) $rec->amount,
                'used' => null,
                'available' => $fundAvail,
                'fund_name' => $rec->fund->fund_name ?? null,
                'budget_remaining' => $rec->budgetPlan ? (float) $rec->budgetPlan->remaining_amount : null,
                'budget_label' => $rec->budgetPlan ? "#{$rec->budgetPlan->id} {$rec->budgetPlan->budget_name}" : null,
            ]);
        }

        if ($kind === 'expense') {
            $rec->load(['budgetPlan']);
            return response()->json([
                'found' => true,
                'kind' => $kind,
                'id' => $rec->id,
                'label' => "#{$rec->id} {$rec->reference_number} — {$rec->payee}",
                'department' => $rec->department,
                'status' => $rec->approval_status,
                'allocated' => (float) $rec->amount,
                'used' => null,
                'available' => $rec->budgetPlan ? (float) $rec->budgetPlan->remaining_amount : null,
                'budget_label' => $rec->budgetPlan ? "#{$rec->budgetPlan->id} {$rec->budgetPlan->budget_name}" : null,
            ]);
        }

        // payable
        return response()->json([
            'found' => true,
            'kind' => $kind,
            'id' => $rec->id,
            'label' => "#{$rec->id} {$rec->invoice_number} — {$rec->vendor}",
            'department' => null,
            'status' => $rec->approval_status,
            'allocated' => (float) $rec->amount,
            'used' => (float) $rec->amount_paid,
            'available' => (float) $rec->remaining_balance,
        ]);
    }

    /**
     * JSON list of budget plans under a department (with remaining).
     * Para makita agad kung magkano ang budget ng department ng nag-request.
     * GET /accountant/financial-requests/department-budgets?department=College...
     */
    public function departmentBudgets(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $dept = trim((string) $request->query('department', ''));
        if ($dept === '') {
            return response()->json(['budgets' => []]);
        }
        $budgets = BudgetPlan::where('department', $dept)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->budget_name,
                'academic_year' => $b->academic_year,
                'status' => $b->status,
                'allocated' => (float) $b->allocated_amount,
                'used' => (float) $b->utilized_amount,
                'remaining' => (float) $b->remaining_amount,
            ]);
        $totalRemaining = $budgets->sum('remaining');
        return response()->json(['budgets' => $budgets, 'total_remaining' => $totalRemaining, 'count' => $budgets->count()]);
    }

    /**
     * Hanapin ang supplier ng isang item (item → supplier).
     * GET /accountant/financial-requests/item-lookup?name=Bond%20Paper
     */
    public function itemLookup(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->query('name', '')));
        if ($name === '') {
            return response()->json(['found' => false]);
        }
        $row = SupplierItem::with('supplier')->active()
            ->get()
            ->first(fn($r) => mb_strtolower($r->item_name) === mb_strtolower($name));
        if (! $row) {
            $suggest = SupplierItem::with('supplier')->active()
                ->where('item_name', 'like', "%{$name}%")
                ->orderBy('item_name')
                ->limit(10)
                ->get()
                ->map(fn($r) => ['item_name' => $r->item_name, 'category' => $r->category, 'supplier' => $r->supplier->name ?? null, 'unit_cost' => $r->last_unit_cost !== null ? (float) $r->last_unit_cost : null]);
            return response()->json(['found' => false, 'suggestions' => $suggest]);
        }
        return response()->json([
            'found' => true,
            'item_name' => $row->item_name,
            'category' => $row->category,
            'supplier' => $row->supplier->name ?? null,
            'unit_cost' => $row->last_unit_cost !== null ? (float) $row->last_unit_cost : null,
        ]);
    }

    /**
     * Listahan ng items ng isang supplier (supplier → items).
     * GET /accountant/financial-requests/supplier-items?supplier=ABC
     */
    public function supplierItems(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->query('supplier', '')));
        if ($name === '') {
            return response()->json(['items' => []]);
        }
        $sup = Supplier::all()->first(fn($s) => mb_strtolower($s->name) === mb_strtolower($name));
        if (! $sup) {
            return response()->json(['items' => []]);
        }
        $items = SupplierItem::active()->where('supplier_id', $sup->id)
            ->orderBy('item_name')
            ->limit(100)
            ->get()
            ->map(fn($r) => ['item_name' => $r->item_name, 'category' => $r->category, 'unit_cost' => $r->last_unit_cost !== null ? (float) $r->last_unit_cost : null]);
        return response()->json(['items' => $items, 'supplier' => $sup->name, 'count' => $items->count()]);
    }
}
