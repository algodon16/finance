<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\FinancialCharge;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivableController extends Controller
{
    public function index(Request $request)
    {
        $q = StudentAccount::with('student');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->whereHas('student', fn($ss) => $ss->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")->orWhere('student_number', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) {
            $q->where('clearance_status', $request->status);
        }
        $sort = $request->get('sort', 'outstanding_balance');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $q->orderBy(in_array($sort, ['outstanding_balance', 'total_charges', 'total_paid']) ? $sort : 'outstanding_balance', $dir);
        $records = $q->paginate(15)->withQueryString();

        $summary = [
            'assessed' => (float) StudentAccount::sum('total_charges'),
            'collected' => (float) StudentAccount::sum('total_paid'),
            'outstanding' => (float) StudentAccount::where('outstanding_balance', '>', 0)->sum('outstanding_balance'),
            'cleared' => StudentAccount::where('clearance_status', 'cleared')->count(),
            'with_balance' => StudentAccount::where('outstanding_balance', '>', 0)->count(),
        ];
        return view('admin.fms.receivables.index', compact('records', 'summary'));
    }

    public function show(StudentAccount $receivable)
    {
        $receivable->load('student');
        // Server-side recalculation from source tables (never trust stored value blindly).
        $charges = (float) FinancialCharge::where('student_id', $receivable->student_id)->sum('amount');
        $paid = (float) Payment::where('student_id', $receivable->student_id)
            ->where(fn($q) => $q->where('status', 'approved')->orWhere('status', 'verified')->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled'))
            ->sum('amount');
        $computedBalance = max(0, (float) ($receivable->total_charges ?? $charges) - $paid);
        $payments = Payment::where('student_id', $receivable->student_id)->orderBy('payment_date', 'desc')->take(50)->get();
        $ledger = \App\Models\AccountLedger::where('student_id', $receivable->student_id)->orderBy('created_at', 'desc')->take(50)->get();
        $receivables = \App\Models\AccountReceivable::where('student_id', $receivable->student_id)->orderBy('due_date')->orderBy('id')->get();
        return view('admin.fms.receivables.show', compact('receivable', 'payments', 'ledger', 'computedBalance', 'paid', 'receivables'));
    }

    public function assess(Request $request)
    {
        $students = Student::orderBy('last_name')->take(500)->get();
        return view('admin.fms.receivables.assess', compact('students'));
    }

    public function storeAssessment(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'description' => 'required|string|max:1000',
            'due_date' => 'nullable|date',
        ]);
        DB::transaction(function () use ($data) {
            $charge = FinancialCharge::create([
                'student_id' => $data['student_id'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'due_date' => $data['due_date'] ?? today()->addDays(30),
                'status' => 'active',
            ]);
            \App\Models\AccountReceivable::create([
                'reference_number' => \App\Models\AccountReceivable::nextReferenceNumber(),
                'student_id' => $data['student_id'],
                'financial_charge_id' => $charge->id,
                'description' => $data['description'],
                'billed_amount' => $data['amount'],
                'paid_amount' => 0,
                'balance' => $data['amount'],
                'due_date' => $charge->due_date,
                'status' => 'open',
                'assessed_by' => auth()->id(),
            ]);
            $account = StudentAccount::firstOrNew(['student_id' => $data['student_id']]);
            $account->total_charges = (float) ($account->total_charges ?? 0) + (float) $data['amount'];
            $account->outstanding_balance = max(0, (float) $account->total_charges - (float) ($account->total_paid ?? 0));
            $account->clearance_status = $account->outstanding_balance <= 0 ? 'cleared' : 'not_cleared';
            $account->save();
        });
        AuditService::log('assess', 'accounts_receivable', (string) $data['student_id'], null, null, "Admin ".auth()->user()->name." assessed P".number_format($data['amount'], 2)." to student #{$data['student_id']}.");
        return redirect()->route('admin.receivables.index')->with('success', 'Fee assessment posted.');
    }
}
