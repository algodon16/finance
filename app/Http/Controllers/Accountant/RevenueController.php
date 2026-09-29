<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

/**
 * Accountant → Revenue Management (preparation side).
 * Same `payments` table as Admin → Revenue Management (review side).
 * payments.status enum has no draft/submitted: pending = Draft, under_review = Pending Approval.
 */
class RevenueController extends Controller
{
    public const DRAFT = 'pending';
    public const PENDING = 'under_review';

    public function index(Request $request)
    {
        $q = Payment::with('student')->orderByDesc('payment_date');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")
                ->orWhere('description', 'ilike', "%{$s}%")
                ->orWhereHas('student', fn($ss) => $ss->where('student_number', 'ilike', "%{$s}%")->orWhere('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")));
        }
        if ($request->filled('status')) {
            $q->where('status', $this->toDbStatus($request->status));
        }
        if ($request->filled('payment_method')) $q->where('payment_method', $request->payment_method);
        if ($request->filled('date_from')) $q->whereDate('payment_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('payment_date', '<=', $request->date_to);
        $records = $q->paginate(15)->withQueryString();
        $summary = [
            'draft' => (int) Payment::where('status', 'pending')->count(),
            'pending' => (int) Payment::where('status', 'under_review')->count(),
            'approved' => (int) Payment::whereIn('status', ['approved', 'posted', 'verified'])->count(),
            'total' => (float) Payment::whereIn('status', ['approved', 'posted', 'verified'])->sum('amount'),
        ];
        return view('accountant.revenue.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('accountant.revenue.form', [
            'record' => new Payment(),
            'students' => Student::orderBy('last_name')->take(500)->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'required|in:cash,bank_transfer,gcash,maya,other',
            'payment_date' => 'required|date|before_or_equal:today',
            'fee_category' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:payments,reference_number',
            'description' => 'nullable|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['proof_of_payment'] = $request->file('supporting_document')->store('payment-proofs', 'public');
        }
        unset($data['supporting_document']);
        $data['status'] = self::DRAFT;
        $data['verification_status'] = 'pending';
        $record = Payment::create($data);
        AuditService::log('create', 'payments', (string) $record->id, null, null, "Accountant ".auth()->user()->name." recorded Revenue Transaction #{$record->id} (P".number_format($record->amount, 2).").");
        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'payments', 'Revenue Transaction', 'status', [self::DRAFT, 'rejected', 'cancelled'], self::PENDING);
            return redirect()->route('accountant.revenue.index')->with('success', 'Revenue transaction submitted. Same record is now visible in Admin → Revenue Management as pending.');
        }
        return redirect()->route('accountant.revenue.index')->with('success', 'Revenue transaction saved as draft.');
    }

    public function show(Payment $revenue)
    {
        $revenue->load(['student.studentAccount', 'paymentProofs', 'reviewer', 'accountLedgerEntries']);
        $history = AuditLog::whereIn('module', ['payments', 'revenues'])->where('record_id', (string) $revenue->id)->latest('id')->take(20)->get();
        return view('accountant.revenue.show', ['record' => $revenue, 'history' => $history]);
    }

    public function edit(Payment $revenue)
    {
        abort_if(! in_array($revenue->status, [self::DRAFT, 'rejected', 'cancelled'], true), 422, 'Only draft or rejected transactions can be edited.');
        return view('accountant.revenue.form', [
            'record' => $revenue,
            'students' => Student::orderBy('last_name')->take(500)->get(),
        ]);
    }

    public function update(Request $request, Payment $revenue)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($revenue->status, [self::DRAFT, 'rejected', 'cancelled'], true), 422, 'Approved/submitted transactions cannot be edited directly.');
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'required|in:cash,bank_transfer,gcash,maya,other',
            'payment_date' => 'required|date|before_or_equal:today',
            'fee_category' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:payments,reference_number,'.$revenue->id,
            'description' => 'nullable|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        if ($request->hasFile('supporting_document')) {
            $data['proof_of_payment'] = $request->file('supporting_document')->store('payment-proofs', 'public');
        }
        unset($data['supporting_document']);
        $old = $revenue->toArray();
        $data['status'] = self::DRAFT;
        $data['rejection_reason'] = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($revenue, $data) {
            $revenue->update($data);
            \App\Models\AccountReceivable::resyncPayment($revenue->fresh());
        });
        AuditService::log('update', 'payments', (string) $revenue->id, ['status' => $old['status']], ['status' => self::DRAFT], "Accountant ".auth()->user()->name." revised Revenue Transaction #{$revenue->id}. Same record, no duplicate.");
        return redirect()->route('accountant.revenue.show', $revenue)->with('success', 'Transaction revised and saved as draft.');
    }

    public function submit(Payment $revenue)
    {
        abort_unless(auth()->user()->role === 'accountant', 403, 'Accountant cannot approve own submission.');
        WorkflowService::submit($revenue, 'payments', 'Revenue Transaction', 'status', [self::DRAFT, 'rejected', 'cancelled'], self::PENDING);
        return back()->with('success', 'Submitted. Same record is now pending in Admin → Revenue Management.');
    }

    public function cancel(Payment $revenue)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if($revenue->status !== self::PENDING, 422, 'Only pending transactions can be withdrawn.');
        $revenue->update(['status' => self::DRAFT, 'submitted_at' => null]);
        AuditService::log('cancel', 'payments', (string) $revenue->id, ['status' => self::PENDING], ['status' => self::DRAFT], "Accountant ".auth()->user()->name." withdrew Revenue Transaction #{$revenue->id}.");
        return back()->with('success', 'Submission withdrawn to draft.');
    }

    /** Verify payment information (not final approval) — migrated from Payment Records. */
    public function verify(Request $request, Payment $revenue)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(in_array($revenue->status, ['approved', 'posted', 'cancelled'], true), 422, 'Closed transactions cannot be verified.');
        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($revenue, $data) {
            $revenue->update([
                'verification_status' => 'verified',
                'verification_message' => $data['remarks'] ?? $revenue->verification_message,
                'verified_at' => now(), 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            ]);
            \App\Models\AccountReceivable::resyncPayment($revenue->fresh());
        });
        AuditService::log('verify', 'payments', (string) $revenue->id, null, null, "Accountant ".auth()->user()->name." verified Revenue Transaction #{$revenue->id}.");
        return back()->with('success', 'Payment information verified.');
    }

    public function rejectInvalid(Request $request, Payment $revenue)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(in_array($revenue->status, ['approved', 'posted', 'cancelled'], true), 422, 'Closed transactions cannot be rejected.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $revenue, $data) {
            $revenue->update([
                'verification_status' => 'rejected', 'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            ]);
            \App\Models\AccountReceivable::resyncPayment($revenue->fresh());
        });
        AuditService::log('reject', 'payments', (string) $revenue->id, null, null, "Accountant ".auth()->user()->name." rejected Revenue Transaction #{$revenue->id}: {$data['rejection_reason']}");
        return back()->with('success', 'Transaction rejected with remarks.');
    }

    protected function toDbStatus(string $ui): string
    {
        return match ($ui) {
            'draft' => self::DRAFT, 'submitted' => self::PENDING,
            'approved' => 'approved', 'rejected' => 'rejected', 'cancelled' => 'cancelled',
            default => $ui,
        };
    }
}
