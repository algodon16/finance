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
        $allowed = ['submit' => 'submitted', 'review' => 'under_review', 'approve' => 'approved', 'reject' => 'rejected', 'order' => 'ordered', 'fulfill' => 'fulfilled', 'cancel' => 'cancelled'];
        abort_unless(isset($allowed[$action]), 404);
        $old = $procurement->toArray();
        $procurement->update(['status' => $allowed[$action], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        AuditService::log($action, 'procurement_requests', (string) $procurement->id, $old, null, "Admin ".auth()->user()->name." set Procurement Request #{$procurement->request_number} to {$allowed[$action]}.");
        return back()->with('success', 'Request '.$allowed[$action].'.');
    }
}
