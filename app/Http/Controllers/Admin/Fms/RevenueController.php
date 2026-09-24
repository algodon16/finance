<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::with('student');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('reference_number', 'ilike', "%{$s}%")
                ->orWhere('transaction_number', 'ilike', "%{$s}%")
                ->orWhereHas('student', fn($ss) => $ss->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")->orWhere('student_number', 'ilike', "%{$s}%")));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('verification_status')) $q->where('verification_status', $request->verification_status);
        if ($request->filled('payment_method')) $q->where('payment_method', $request->payment_method);
        if ($request->filled('fee_category')) $q->where('fee_category', $request->fee_category);
        if ($request->filled('date_from')) $q->whereDate('payment_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('payment_date', '<=', $request->date_to);
        $sort = $request->get('sort', 'payment_date');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $q->orderBy(in_array($sort, ['payment_date', 'amount', 'created_at']) ? $sort : 'payment_date', $dir);

        $records = $q->paginate(15)->withQueryString();

        // Summaries (server-side).
        $base = Payment::where(function ($w) {
            $w->where('status', 'approved')->orWhere('status', 'verified')
              ->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled');
        });
        $summary = [
            'total' => (float) (clone $base)->sum('amount'),
            'daily' => (float) (clone $base)->whereDate('payment_date', today())->sum('amount'),
            'monthly' => (float) (clone $base)->whereYear('payment_date', today()->year)->whereMonth('payment_date', today()->month)->sum('amount'),
            'annual' => (float) (clone $base)->whereYear('payment_date', today()->year)->sum('amount'),
        ];
        $byCategory = (clone $base)->select('fee_category', DB::raw('SUM(amount) as t'))->groupBy('fee_category')->get();
        $byMethod = (clone $base)->select('payment_method', DB::raw('SUM(amount) as t'))->groupBy('payment_method')->get();

        return view('admin.fms.revenues.index', compact('records', 'summary', 'byCategory', 'byMethod'));
    }

    public function create()
    {
        $students = Student::orderBy('last_name')->take(500)->get();
        return view('admin.fms.revenues.form', ['record' => new Payment(), 'students' => $students]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'transaction_number' => 'nullable|string|max:100|unique:payments,transaction_number',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'fee_category' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:payments,reference_number',
            'description' => 'nullable|string|max:2000',
            'payment_status' => 'nullable|in:pending,verified,rejected,reconciled',
        ]);
        $record = DB::transaction(function () use ($data) {
            $p = Payment::create([
                'student_id' => $data['student_id'],
                'transaction_number' => $data['transaction_number'] ?? ('TRX-'.now()->format('Ymd').'-'.strtoupper(uniqid())),
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'fee_category' => $data['fee_category'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['payment_status'] ?? 'pending',
                'verification_status' => $data['payment_status'] ?? 'pending',
            ]);
            $this->syncStudentAccount($p->student_id);
            return $p;
        });
        AuditService::log('create', 'revenues', (string) $record->id, null, null, "Admin ".auth()->user()->name." recorded Revenue Transaction #{$record->transaction_number} (P".number_format($record->amount, 2).").");
        return redirect()->route('admin.revenues.index')->with('success', 'Payment recorded.');
    }

    public function show(Payment $revenue)
    {
        $revenue->load(['student', 'paymentProofs', 'reviewer']);
        return view('admin.fms.revenues.show', ['record' => $revenue]);
    }

    public function edit(Payment $revenue)
    {
        $students = Student::orderBy('last_name')->take(500)->get();
        return view('admin.fms.revenues.form', ['record' => $revenue, 'students' => $students]);
    }

    public function update(Request $request, Payment $revenue)
    {
        $data = $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'fee_category' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:payments,reference_number,'.$revenue->id,
            'description' => 'nullable|string|max:2000',
        ]);
        $old = $revenue->toArray();
        DB::transaction(function () use ($revenue, $data) {
            $revenue->update($data);
            $this->syncStudentAccount($revenue->student_id);
        });
        AuditService::log('update', 'revenues', (string) $revenue->id, $old, $revenue->fresh()->toArray(), "Admin ".auth()->user()->name." updated Revenue Transaction #{$revenue->transaction_number}.");
        return redirect()->route('admin.revenues.show', $revenue)->with('success', 'Payment updated.');
    }

    public function setStatus(Payment $revenue, string $action)
    {
        abort_unless(in_array($action, ['verify', 'reject', 'reconcile']), 404);
        $map = ['verify' => ['status' => 'verified', 'verification_status' => 'verified'], 'reject' => ['status' => 'rejected', 'verification_status' => 'rejected'], 'reconcile' => ['status' => 'verified', 'verification_status' => 'reconciled']];
        $old = $revenue->toArray();
        DB::transaction(function () use ($revenue, $map, $action) {
            $revenue->update($map[$action] + ['reviewed_by' => auth()->id(), 'reviewed_at' => now()] + ($action === 'reconcile' ? ['reconciled_at' => now()] : []));
            $this->syncStudentAccount($revenue->student_id);
        });
        AuditService::log($action, 'revenues', (string) $revenue->id, $old, null, "Admin ".auth()->user()->name." {$action}d Revenue Transaction #{$revenue->transaction_number}.");
        return back()->with('success', 'Payment '.$action.'d.');
    }

    public function export(Request $request)
    {
        $records = Payment::with('student')->orderBy('payment_date', 'desc')->take(5000)->get();
        AuditService::log('export', 'revenues', null, null, null, "Admin ".auth()->user()->name." exported revenue records (CSV).");
        $csv = "Transaction Number,Student,Student Number,Date,Method,Category,Amount,Status,Verification,Reference\n";
        foreach ($records as $r) {
            $csv .= '"'.($r->transaction_number ?? '').'","'.($r->student->full_name ?? '').'","'.($r->student->student_number ?? '').'","'.$r->payment_date.'","'.$r->payment_method.'","'.($r->fee_category ?? '').'","'.$r->amount.'","'.$r->status.'","'.$r->verification_status.'","'.($r->reference_number ?? '')."\"\n";
        }
        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="revenue-export.csv"']);
    }

    /** Server-side: total_paid = SUM(approved/verified payments); balance = charges - paid. */
    protected function syncStudentAccount(int $studentId): void
    {
        $account = \App\Models\StudentAccount::firstOrNew(['student_id' => $studentId]);
        $paid = (float) Payment::where('student_id', $studentId)
            ->where(fn($q) => $q->where('status', 'approved')->orWhere('status', 'verified')->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled'))
            ->sum('amount');
        $account->total_paid = $paid;
        $charges = (float) ($account->total_charges ?? 0);
        $account->outstanding_balance = max(0, $charges - $paid);
        $account->clearance_status = $account->outstanding_balance <= 0 ? 'cleared' : 'not_cleared';
        $account->save();
    }
}
