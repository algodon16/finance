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
        $payable->load('payments');
        return view('admin.fms.payables.show', ['record' => $payable]);
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
        });
        AuditService::log('pay', 'accounts_payable', (string) $payable->id, null, null, "Admin ".auth()->user()->name." posted P".number_format($data['amount'], 2)." payment to Invoice #{$payable->invoice_number}.");
        return back()->with('success', 'Payment posted.');
    }

    public function destroy(AccountsPayable $payable)
    {
        abort_if((float) $payable->amount_paid > 0, 422, 'Payables with payments cannot be deleted.');
        $old = $payable->toArray();
        $payable->delete();
        AuditService::log('delete', 'accounts_payable', (string) $payable->id, $old, null, "Admin ".auth()->user()->name." deleted Payable Invoice #{$old['invoice_number']}.");
        return redirect()->route('admin.payables.index')->with('success', 'Payable deleted.');
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
