<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function index(Request $request)
    {
        // Overview = accountant Financial Requests for admin approval (same records as inbox).
        $q = \App\Models\FinancialRequest::with(['preparer', 'student', 'budgetPlan']);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('request_number', 'ilike', "%{$s}%")
                ->orWhere('source_request_id', 'ilike', "%{$s}%")
                ->orWhere('department', 'ilike', "%{$s}%")
                ->orWhere('description', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        $records = $q->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $summary = [
            'draft' => \App\Models\FinancialRequest::where('status', 'draft')->count(),
            'submitted' => \App\Models\FinancialRequest::where('status', 'submitted')->count(),
            'under_review' => \App\Models\FinancialRequest::where('status', 'under_review')->count(),
            'approved' => \App\Models\FinancialRequest::where('status', 'approved')->count(),
            'fulfilled' => \App\Models\FinancialRequest::where('status', 'completed')->count(),
        ];
        return view('admin.fms.procurement.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('admin.fms.procurement.form', ['record' => new ProcurementRequest()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requesting_department' => 'required|string|max:255',
            'item_description' => 'required|string|max:2000',
            'quantity' => 'required|integer|min:1|max:100000',
            'estimated_cost' => 'required|numeric|min:0.01|max:999999999999.99',
            'supplier' => 'nullable|string|max:255',
            'justification' => 'required|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'status' => 'required|in:draft,submitted,under_review,approved,rejected,ordered,fulfilled,cancelled',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('procurement-docs', 'public');
        }
        $data['total_amount'] = $data['estimated_cost'];
        $data['request_number'] = 'PR-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        $record = ProcurementRequest::create($data);
        AuditService::log('create', 'procurement_requests', (string) $record->id, null, null, "Admin ".auth()->user()->name." created Procurement Request #{$record->request_number}.");
        return redirect()->route('admin.procurement.index')->with('success', 'Request created.');
    }

    public function show(ProcurementRequest $procurement)
    {
        $procurement->load(['student', 'items', 'reviewer']);
        return view('admin.fms.procurement.show', ['record' => $procurement]);
    }

    public function edit(ProcurementRequest $procurement)
    {
        return view('admin.fms.procurement.form', ['record' => $procurement]);
    }

    public function update(Request $request, ProcurementRequest $procurement)
    {
        $data = $request->validate([
            'requesting_department' => 'required|string|max:255',
            'item_description' => 'required|string|max:2000',
            'quantity' => 'required|integer|min:1|max:100000',
            'estimated_cost' => 'required|numeric|min:0.01|max:999999999999.99',
            'supplier' => 'nullable|string|max:255',
            'justification' => 'required|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('procurement-docs', 'public');
        }
        $data['total_amount'] = $data['estimated_cost'];
        $old = $procurement->toArray();
        $procurement->update($data);
        AuditService::log('update', 'procurement_requests', (string) $procurement->id, $old, $procurement->fresh()->toArray(), "Admin ".auth()->user()->name." updated Procurement Request #{$procurement->request_number}.");
        return redirect()->route('admin.procurement.show', $procurement)->with('success', 'Request updated.');
    }

    public function setStatus(ProcurementRequest $procurement, string $action)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        $allowed = ['submit' => 'submitted', 'review' => 'under_review', 'approve' => 'approved', 'reject' => 'rejected', 'order' => 'ordered', 'fulfill' => 'fulfilled', 'cancel' => 'cancelled'];
        abort_unless(isset($allowed[$action]), 404);
        if ($action === 'reject') request()->validate(['rejection_reason' => 'required|string|max:5000']);
        $old = $procurement->toArray();
        $patch = ['status' => $allowed[$action], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()];
        if ($action === 'reject') {
            $patch['rejection_reason'] = request('rejection_reason');
            $patch['admin_remarks'] = request('admin_remarks');
        }
        $procurement->update($patch);
        AuditService::log($action, 'procurement_requests', (string) $procurement->id, ['status' => $old['status']], ['status' => $allowed[$action]], "Admin ".auth()->user()->name." set Procurement Request #{$procurement->request_number} to {$allowed[$action]}.");
        return back()->with('success', 'Request '.$allowed[$action].'.');
    }

    /** Financial Requests inbox — same FinancialRequest records the Accountant submitted. */
    public function financialRequests(Request $request)
    {
        $q = \App\Models\FinancialRequest::with(['preparer', 'student', 'budgetPlan'])->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('request_type')) $q->where('request_type', $request->request_type);
        $records = $q->paginate(15)->withQueryString();
        $counts = [
            'pending' => (int) \App\Models\FinancialRequest::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (int) \App\Models\FinancialRequest::whereIn('status', ['approved', 'completed'])->count(),
            'rejected' => (int) \App\Models\FinancialRequest::whereIn('status', ['rejected', 'for_revision', 'revision'])->count(),
        ];
        $departments = \App\Models\Department::active()->orderBy('name')->pluck('name');
        $budgets = \App\Models\BudgetPlan::whereIn('status', ['approved', 'active'])->orderByDesc('id')->limit(200)->get();
        return view('admin.fms.financial-requests.index', compact('records', 'counts', 'departments', 'budgets'));
    }

    public function showFinancialRequest(\App\Models\FinancialRequest $request)
    {
        $request->load(['preparer', 'student', 'budgetPlan', 'payable.disbursement', 'payable.payments']);
        $linked = null;
        if ($request->reference_type && $request->reference_id && class_exists($request->reference_type)) {
            try { $linked = $request->reference_type::find($request->reference_id); } catch (\Throwable $e) {}
        }
        $history = \App\Models\AuditLog::with('user')->where('module', 'financial_requests')->where('record_id', (string) $request->id)->latest('id')->take(30)->get();
        $validation = $request->budget_plan_id
            ? \App\Models\FinancialRequest::budgetValidation($request->budgetPlan, (float) $request->amount, $request->id)
            : null;
        return view('admin.fms.financial-requests.show', ['record' => $request, 'linked' => $linked, 'history' => $history, 'validation' => $validation]);
    }

    public function approveFinancialRequest(Request $http, \App\Models\FinancialRequest $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($request->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be approved.');
        $http->validate(['admin_remarks' => 'nullable|string|max:60']);
        // Budget validation gate: linked budget must cover the request.
        if ($request->budget_plan_id) {
            $v = \App\Models\FinancialRequest::budgetValidation($request->budgetPlan, (float) $request->amount, $request->id);
            abort_if(! $v, 422, 'Linked budget is not approved/active.');
            abort_if(! $v['valid'], 422, 'Budget insufficient: requested P'.number_format((float) $request->amount, 2).' exceeds available P'.number_format($v['available'], 2).'.');
        }

        $fulfilled = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($http, $request, &$fulfilled) {
            $request->update([
                'status' => 'approved', 'admin_decision' => 'approved',
                'admin_remarks' => $http->input('admin_remarks') ?: $request->admin_remarks,
                'decided_by' => auth()->id(), 'decided_at' => now(),
                'reviewed_at' => $request->reviewed_at ?: now(),
            ]);
            // Assessment adjustments post to the real ledger only on approval.
            if ($request->request_type === 'assessment_adjustment' && $request->student_id) {
                $charge = \App\Models\FinancialCharge::create([
                    'student_id' => $request->student_id, 'description' => $request->description,
                    'amount' => $request->amount, 'due_date' => now()->addDays(30)->toDateString(), 'status' => 'active',
                ]);
                \App\Models\AccountReceivable::create([
                    'reference_number' => \App\Models\AccountReceivable::nextReferenceNumber(),
                    'student_id' => $request->student_id,
                    'financial_charge_id' => $charge->id,
                    'description' => $request->description,
                    'billed_amount' => $request->amount,
                    'paid_amount' => 0,
                    'balance' => $request->amount,
                    'due_date' => $charge->due_date,
                    'status' => 'open',
                    'assessed_by' => auth()->id(),
                ]);
                $account = \App\Models\StudentAccount::firstOrCreate(['student_id' => $request->student_id]);
                $account->increment('total_charges', $request->amount);
                $account->increment('outstanding_balance', $request->amount);
                $request->update(['reference_type' => \App\Models\FinancialCharge::class, 'reference_id' => $charge->id]);
            }
            // Auto-fulfillment inside same transaction (atomic with approval).
            if ($request->request_type !== 'assessment_adjustment') {
                $fulfilled = \App\Services\ApDisbursementService::generateFromFinancialRequest($request->fresh());
            }
        });

        AuditService::log('approve', 'financial_requests', (string) $request->id, ['status' => 'submitted'], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Financial Request {$request->request_number} — corresponding module updated, no duplicate.");
        \App\Services\WorkflowService::notifyUser($request->prepared_by, "Financial Request {$request->request_number} has been approved.", $fulfilled ? ("It is now FOR PROCESSING in Expense & Disbursement Tracking as {$fulfilled['expense']->reference_number} (linked {$fulfilled['payable']->ap_number}).") : "It is now reflected in the corresponding module.");
        return back()->with('success', $fulfilled ? ('Request approved. Auto-forwarded to Expense & Disbursement Tracking as '.$fulfilled['expense']->reference_number.' (FOR PROCESSING).') : 'Request approved. Same record updated.');
    }

    public function rejectFinancialRequest(Request $http, \App\Models\FinancialRequest $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $http->validate(['rejection_reason' => 'required|string|max:60', 'admin_remarks' => 'nullable|string|max:60']);
        abort_if(! in_array($request->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be rejected.');
        $request->update([
            'status' => 'rejected', 'admin_decision' => 'rejected',
            'admin_remarks' => $data['admin_remarks'] ?? $request->admin_remarks,
            'decided_by' => auth()->id(), 'decided_at' => now(),
            'revision_number' => ((int) $request->revision_number) + 1,
            'metadata' => array_merge($request->metadata ?? [], ['rejection_reason' => $data['rejection_reason']]),
        ]);
        AuditService::log('reject', 'financial_requests', (string) $request->id, ['status' => 'submitted'], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Financial Request {$request->request_number}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($request->prepared_by, "Financial Request {$request->request_number} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit.");
        return back()->with('success', 'Request rejected and returned for revision.');
    }

    /** Demo intake for admins — same validated path as the accountant side. */
    public function simulateFinancialRequest(Request $http)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can simulate intake.');
        $data = $http->validate([
            'source_system' => 'required|string|max:50|in:'.implode(',', array_keys(array_filter(\App\Models\FinancialRequest::SOURCES, fn($k) => $k !== 'internal', ARRAY_FILTER_USE_KEY))),
            'request_type' => 'required|string|max:50',
            'department' => 'required|string|max:255|exists:departments,name',
            'source_request_id' => 'nullable|string|max:100',
            'purpose' => 'required|string|max:5000',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'budget_plan_id' => 'required|exists:budget_plans,id',
        ]);
        $record = \App\Models\FinancialRequest::receiveSimulated($data, auth()->id());
        return redirect()->route('admin.financial-requests.show', $record)->with('success', 'Demo request received as '.$record->display_ref.'.');
    }

    /** Return a request for revision (remarks required) — record preserved. */
    public function returnFinancialRequest(Request $http, \App\Models\FinancialRequest $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can return requests.');
        $data = $http->validate(['admin_remarks' => 'required|string|max:60']);
        abort_if(! in_array($request->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be returned.');
        $old = $request->status;
        $request->update([
            'status' => 'for_revision',
            'admin_remarks' => $data['admin_remarks'],
            'decided_by' => auth()->id(), 'decided_at' => now(),
            'revision_number' => ((int) $request->revision_number) + 1,
        ]);
        AuditService::log('revise', 'financial_requests', (string) $request->id, ['status' => $old], ['status' => 'for_revision'], "Admin ".auth()->user()->name." returned Financial Request {$request->request_number} for revision: {$data['admin_remarks']}");
        \App\Services\WorkflowService::notifyUser($request->prepared_by, "Financial Request {$request->request_number} returned for revision.", "Admin comment: {$data['admin_remarks']}");
        return back()->with('success', 'Request returned for revision.');
    }
}
