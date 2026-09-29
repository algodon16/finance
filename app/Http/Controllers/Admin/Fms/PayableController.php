<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\PayablePayment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $q = AccountsPayable::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('vendor', 'ilike', "%{$s}%")->orWhere('invoice_number', 'ilike', "%{$s}%"));
        }
        if ($request->filled('payment_status')) $q->where('payment_status', $request->payment_status);
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
        if ($request->filled('date_from')) $q->whereDate('due_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('due_date', '<=', $request->date_to);
        $records = $q->orderBy('due_date')->paginate(15)->withQueryString();

        $all = AccountsPayable::all();
        $summary = [
            'total' => (float) $all->sum('amount'),
            'paid' => (float) $all->sum('amount_paid'),
            'remaining' => (float) $all->sum(fn($p) => (float) $p->remaining_balance),
            'overdue' => (float) $all->filter(fn($p) => $p->derived_status === 'Overdue')->sum(fn($p) => (float) $p->remaining_balance),
            'due_soon' => (float) $all->filter(fn($p) => $p->derived_status === 'Due Soon')->sum(fn($p) => (float) $p->remaining_balance),
        ];
        return view('admin.fms.payables.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('admin.fms.payables.form', ['record' => new AccountsPayable()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:100|unique:accounts_payable,invoice_number',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'payment_schedule' => 'nullable|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('payable-docs', 'public');
        }
        $data['amount_paid'] = 0;
        $data['payment_status'] = 'pending';
        $data['created_by'] = auth()->id();
        $record = AccountsPayable::create($data);
        AuditService::log('create', 'accounts_payable', (string) $record->id, null, null, "Admin ".auth()->user()->name." recorded Payable Invoice #{$record->invoice_number} (P".number_format($record->amount, 2).").");
        return redirect()->route('admin.payables.index')->with('success', 'Payable recorded.');
    }

    public function show(AccountsPayable $payable)
    {
        $payable->load(['payments', 'expense', 'budgetPlan', 'fund', 'disbursement']);
        $history = \App\Models\AuditLog::with('user')->where('module', 'accounts_payable')->where('record_id', (string) $payable->id)->latest('id')->take(30)->get();
        return view('admin.fms.payables.show', ['record' => $payable, 'history' => $history]);
    }

    public function edit(AccountsPayable $payable)
    {
        return view('admin.fms.payables.form', ['record' => $payable]);
    }

    public function update(Request $request, AccountsPayable $payable)
    {
        $data = $request->validate([
            'vendor' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:100|unique:accounts_payable,invoice_number,'.$payable->id,
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'amount' => 'required|numeric|min:'.$payable->amount_paid.'|max:999999999999.99',
            'payment_schedule' => 'nullable|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:2000',
        ], ['amount.min' => 'Amount cannot be less than the amount already paid.']);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('payable-docs', 'public');
        }
        $old = $payable->toArray();
        $payable->update($data);
        $this->syncStatus($payable);
        AuditService::log('update', 'accounts_payable', (string) $payable->id, $old, $payable->fresh()->toArray(), "Admin ".auth()->user()->name." updated Payable Invoice #{$payable->invoice_number}.");
        return redirect()->route('admin.payables.show', $payable)->with('success', 'Payable updated.');
    }

    public function pay(Request $request, AccountsPayable $payable)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.$payable->remaining_balance,
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($payable, $data) {
            PayablePayment::create($data + ['accounts_payable_id' => $payable->id, 'created_by' => auth()->id()]);
            $payable->increment('amount_paid', $data['amount']);
            $payable->refresh();
            $this->syncStatus($payable);
            // Fund moves once per payment; linked disbursement mirrors Paid/Partially Paid.
            \App\Services\ApDisbursementService::moveFund($payable->fund_id, (float) $data['amount'], $payable->invoice_number, 'Payment for '.$payable->ap_number);
            \App\Services\ApDisbursementService::syncFromPayablePayment($payable->fresh());
        });
        AuditService::log('pay', 'accounts_payable', (string) $payable->id, null, null, "Admin ".auth()->user()->name." posted P".number_format($data['amount'], 2)." payment to Invoice #{$payable->invoice_number} (linked disbursement synced).");
        return back()->with('success', 'Payment posted. Linked disbursement synced.');
    }

    public function destroy(AccountsPayable $payable)
    {
        abort_if((float) $payable->amount_paid > 0, 422, 'Payables with payments cannot be deleted.');
        $wasApproved = ($payable->approval_status ?? 'draft') === 'approved';
        $old = $payable->toArray();
        DB::transaction(function () use ($payable, $wasApproved) {
            // A pending auto-disbursement dies with its AP; release the committed budget once.
            \App\Services\ApDisbursementService::cancelFor($payable, 'cancelled/deleted');
            if ($wasApproved) \App\Services\ApDisbursementService::releaseBudget($payable);
            $payable->delete();
        });
        AuditService::log('delete', 'accounts_payable', (string) $payable->id, $old, null, "Admin ".auth()->user()->name." deleted Payable Invoice #{$old['invoice_number']} (linked disbursement cancelled, budget released).");
        return redirect()->route('admin.payables.index')->with('success', 'Payable deleted. Linked disbursement cancelled.');
    }

    /** Approve submitted payable — same record, submitted → approved, then auto-forward to Disbursement. */
    public function approve(Request $request, AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($payable->approval_status ?? 'draft', ['submitted', 'under_review'], true), 422, 'Only submitted payables can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        $old = $payable->approval_status;
        DB::transaction(function () use ($request, $payable) {
            // Commit budget FIRST so over-budget approvals fail before any state change.
            \App\Services\ApDisbursementService::commitBudget($payable->fresh());
            $payable->update([
                'approval_status' => 'approved',
                'payment_status' => $payable->payment_status === 'pending' ? 'approved' : $payable->payment_status,
                'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
                'approved_by' => auth()->id(), 'approved_at' => now(),
                'admin_remarks' => $request->input('admin_remarks') ?: $payable->admin_remarks,
            ]);
            // Auto-generate the single disbursement record (idempotent) — no manual Create Expense needed.
            \App\Services\ApDisbursementService::generateFor($payable->fresh());
        });
        $disbursement = \App\Models\Expense::where('related_payable_id', $payable->id)->first();
        AuditService::log('approve', 'accounts_payable', (string) $payable->id, ['status' => $old], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Payable Invoice #{$payable->invoice_number} — auto-forwarded as Disbursement ".($disbursement->reference_number ?? '—').".");
        \App\Services\WorkflowService::notifyUser($payable->created_by, "Payable Invoice #{$payable->invoice_number} has been approved.", "Disbursement ".($disbursement->reference_number ?? '')." was auto-generated in Expense & Disbursement Tracking.");
        return back()->with('success', 'Payable approved and auto-forwarded to Expense & Disbursement Tracking as '.($disbursement->reference_number ?? 'disbursement').'.');
    }

    /** Reject — same record, submitted → rejected (reason required). */
    public function reject(Request $request, AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($payable->approval_status ?? 'draft', ['submitted', 'under_review'], true), 422, 'Only submitted payables can be rejected.');
        $payable->update([
            'approval_status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $payable->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'revision_number' => ((int) $payable->revision_number) + 1,
        ]);
        AuditService::log('reject', 'accounts_payable', (string) $payable->id, ['status' => 'submitted'], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Payable Invoice #{$payable->invoice_number}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($payable->created_by, "Payable Invoice #{$payable->invoice_number} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit.");
        return back()->with('success', 'Payable rejected and returned for revision.');
    }

    /**
     * Self-healing sync for APs approved before the auto-forward went live
     * (or any approved AP missing its disbursement). Idempotent.
     */
    public function generateDisbursement(AccountsPayable $payable)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can sync.');
        abort_if(($payable->approval_status ?? 'draft') !== 'approved', 422, 'Only approved payables can be forwarded.');
        DB::transaction(function () use ($payable) {
            // Commit budget only together with a first-time generation — never twice.
            if (! \App\Models\Expense::where('related_payable_id', $payable->id)->lockForUpdate()->exists()) {
                \App\Services\ApDisbursementService::commitBudget($payable->fresh());
                \App\Services\ApDisbursementService::generateFor($payable->fresh());
            }
        });
        $dis = \App\Models\Expense::where('related_payable_id', $payable->id)->first();
        return back()->with('success', 'Synced to Expense & Disbursement Tracking as '.($dis->reference_number ?? 'disbursement').'.');
    }

    /** Public wrapper so the disbursement service can reuse the same status rules. */
    public function syncStatusPublic(AccountsPayable $p): void
    {
        $this->syncStatus($p->fresh());
    }

    protected function syncStatus(AccountsPayable $p): void
    {
        $remaining = (float) $p->remaining_balance;
        $status = 'pending';
        if ($remaining <= 0) $status = 'fully_paid';
        elseif ((float) $p->amount_paid > 0) $status = 'partially_paid';
        elseif ($p->due_date < today()) $status = 'overdue';
        elseif ($p->due_date->diffInDays(today()) <= 7) $status = 'due_soon';
        $p->update(['payment_status' => $status]);
    }
}
