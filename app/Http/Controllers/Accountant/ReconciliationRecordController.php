<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReconciliationRecord;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationRecordController extends Controller
{
    public function index(Request $request)
    {
        $q = ReconciliationRecord::with('payment')->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        $records = $q->paginate(15)->withQueryString();
        return view('accountant.reconciliation-records.index', compact('records'));
    }

    public function create(Request $request)
    {
        $payment = $request->filled('payment_id') ? Payment::find($request->payment_id) : null;
        return view('accountant.reconciliation-records.form', ['record' => new ReconciliationRecord(['payment_id' => $payment?->id, 'system_amount' => $payment?->amount ?? 0]), 'payment' => $payment]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'reconciliation_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'system_amount' => 'required|numeric|min:0|max:999999999999.99',
            'actual_amount' => 'required|numeric|min:0|max:999999999999.99',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'payment_id' => 'nullable|exists:payments,id',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $data['reference_number'] = 'REC-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('reconciliation-docs', 'public');
        }
        $data['variance'] = round((float) $data['actual_amount'] - (float) $data['system_amount'], 2);
        $data['status'] = abs((float) $data['variance']) < 0.01 ? 'reconciled' : 'with_variance';
        $data['prepared_by'] = auth()->id();
        $record = ReconciliationRecord::create($data);
        AuditService::log('create', 'reconciliation_records', (string) $record->id, null, null, "Accountant ".auth()->user()->name." prepared Reconciliation {$record->reference_number} (variance P".number_format($record->variance, 2).").");
        return redirect()->route('accountant.reconciliation-records.index')->with('success', 'Reconciliation record prepared.');
    }

    public function show(ReconciliationRecord $reconciliationRecord)
    {
        $reconciliationRecord->load('payment');
        return view('accountant.reconciliation-records.show', ['record' => $reconciliationRecord]);
    }

    public function edit(ReconciliationRecord $reconciliationRecord)
    {
        abort_if(in_array($reconciliationRecord->status, ['submitted', 'reviewed'], true), 422, 'Submitted reconciliations cannot be edited.');
        return view('accountant.reconciliation-records.form', ['record' => $reconciliationRecord, 'payment' => $reconciliationRecord->payment]);
    }

    public function update(Request $request, ReconciliationRecord $reconciliationRecord)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(in_array($reconciliationRecord->status, ['submitted', 'reviewed'], true), 422, 'Submitted reconciliations cannot be edited.');
        $data = $request->validate([
            'reconciliation_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'system_amount' => 'required|numeric|min:0|max:999999999999.99',
            'actual_amount' => 'required|numeric|min:0|max:999999999999.99',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('reconciliation-docs', 'public');
        }
        $old = $reconciliationRecord->toArray();
        DB::transaction(function () use ($reconciliationRecord, $data) {
            $reconciliationRecord->update($data);
            $reconciliationRecord->recalculateVariance();
            $reconciliationRecord->save();
        });
        AuditService::log('reconcile', 'reconciliation_records', (string) $reconciliationRecord->id, $old, $reconciliationRecord->fresh()->toArray(), "Accountant ".auth()->user()->name." reconciled {$reconciliationRecord->reference_number}.");
        return redirect()->route('accountant.reconciliation-records.show', $reconciliationRecord)->with('success', 'Reconciliation updated.');
    }

    public function submit(ReconciliationRecord $reconciliationRecord)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(in_array($reconciliationRecord->status, ['submitted', 'reviewed'], true), 422, 'Already submitted.');
        $old = $reconciliationRecord->status;
        $reconciliationRecord->update(['status' => 'submitted', 'submitted_at' => now()]);
        AuditService::log('submit', 'reconciliation_records', (string) $reconciliationRecord->id, ['status' => $old], ['status' => 'submitted'], "Accountant ".auth()->user()->name." submitted Reconciliation {$reconciliationRecord->reference_number} for admin review.");
        WorkflowService::notifyAdmins('New Reconciliation Submitted', "Reconciliation {$reconciliationRecord->reference_number} submitted by ".auth()->user()->name.".");
        return back()->with('success', 'Reconciliation submitted for admin review.');
    }
}
