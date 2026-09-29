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
        $q = ProcurementRequest::with(['student', 'items', 'reviewer']);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('request_number', 'ilike', "%{$s}%")->orWhere('requesting_department', 'ilike', "%{$s}%")->orWhere('item_description', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        $records = $q->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $summary = [
            'draft' => ProcurementRequest::where('status', 'draft')->count(),
            'submitted' => ProcurementRequest::where('status', 'submitted')->count(),
            'under_review' => ProcurementRequest::where('status', 'under_review')->count(),
            'approved' => ProcurementRequest::where('status', 'approved')->count(),
            'fulfilled' => ProcurementRequest::where('status', 'fulfilled')->count(),
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
        return view('admin.fms.financial-requests.index', compact('records', 'counts'));
    }

    public function showFinancialRequest(\App\Models\FinancialRequest $request)
    {
        $request->load(['preparer', 'student', 'budgetPlan']);
        $linked = null;
        if ($request->reference_type && $request->reference_id && class_exists($request->reference_type)) {
            try { $linked = $request->reference_type::find($request->reference_id); } catch (\Throwable $e) {}
        }
        $history = \App\Models\AuditLog::with('user')->where('module', 'financial_requests')->where('record_id', (string) $request->id)->latest('id')->take(30)->get();
        return view('admin.fms.financial-requests.show', ['record' => $request, 'linked' => $linked, 'history' => $history]);
    }

    public function approveFinancialRequest(Request $http, \App\Models\FinancialRequest $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($request->status, ['submitted', 'under_review'], true), 422, 'Only submitted requests can be approved.');
        $http->validate(['admin_remarks' => 'nullable|string|max:5000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($http, $request) {
            $request->update([
                'status' => 'approved', 'admin_decision' => 'approved',
                'admin_remarks' => $http->input('admin_remarks') ?: $request->admin_remarks,
                'decided_by' => auth()->id(), 'decided_at' => now(),
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
        });
        AuditService::log('approve', 'financial_requests', (string) $request->id, ['status' => 'submitted'], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Financial Request {$request->request_number} — corresponding module updated, no duplicate.");
        \App\Services\WorkflowService::notifyUser($request->prepared_by, "Financial Request {$request->request_number} has been approved.", "It is now reflected in the corresponding module.");
        return back()->with('success', 'Request approved. Same record updated.');
    }

    public function rejectFinancialRequest(Request $http, \App\Models\FinancialRequest $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $http->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
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
}
