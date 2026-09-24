<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Services\AuditService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cashier "Payment" module — records payments made personally by a
 * student at the cashier counter (cash only, no receipt upload, no OCR).
 *
 * Reuses the existing payments table, student database, fee assessment
 * data, PaymentApprovalService workflow, Payment History and Dashboard.
 */
class WalkInPaymentController extends Controller
{
    public function index()
    {
        return view('cashier.payment.index');
    }

    /**
     * Look up a student by Student ID (student_number) for the payment form.
     * Returns the minimum info needed to confirm the correct student plus
     * the fees applicable to that student and their current balance.
     */
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'student_number' => 'required|string|max:255',
        ]);

        $student = Student::where('student_number', $validated['student_number'])
            ->with(['studentAccount'])
            ->first();

        if (! $student) {
            return response()->json([
                'found' => false,
                'message' => 'No student found with that Student ID.',
            ], 404);
        }

        $outstanding = $student->studentAccount
            ? (float) $student->studentAccount->outstanding_balance
            : 0.0;

        return response()->json([
            'found' => true,
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'program' => $student->program,
                'year_level' => $student->year_level,
            ],
            'outstanding_balance' => $outstanding,
            'fees' => $this->applicableFees($student),
        ]);
    }

    public function store(Request $request, PaymentApprovalService $approvals)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'payment_for' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $student = Student::with('studentAccount')->findOrFail($validated['student_id']);

        // The selected fee must be one of the student's applicable fees.
        $feeNames = collect($this->applicableFees($student))->pluck('name')->all();
        if (! in_array($validated['payment_for'], $feeNames, true)) {
            return back()
                ->withErrors(['payment_for' => 'Please select a valid fee applicable to this student.'])
                ->withInput();
        }

        $amount = round((float) $validated['amount'], 2);

        $payment = DB::transaction(function () use ($student, $validated, $amount, $approvals) {
            // Lock the account row so concurrent cashiers cannot overpay.
            $account = StudentAccount::where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            $outstanding = $account ? (float) $account->outstanding_balance : 0.0;

            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This student has no outstanding balance.',
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount cannot be greater than the outstanding balance of ₱' . number_format($outstanding, 2) . '.',
                ]);
            }

            // Guard against a duplicate submission (double-click / resubmit).
            $duplicate = Payment::where('student_id', $student->id)
                ->where('amount', $amount)
                ->where('description', $validated['payment_for'])
                ->where('payment_method', 'cash')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment was already recorded. Please check Payment History.',
                ]);
            }

            $payment = Payment::create([
                'student_id' => $student->id,
                'amount' => $amount,
                'payment_method' => 'cash', // counter payments are cash only
                'reference_number' => $this->generateReceiptNumber(),
                'payment_date' => now()->toDateString(), // server date
                'description' => $validated['payment_for'], // fee allocation
                'status' => 'pending',
            ]);

            AuditService::log('create', 'payment', $payment->id, null, $payment->toArray());

            // Same posting workflow as every other payment: marks approved,
            // records the cashier + server date/time, updates the student's
            // outstanding balance, writes the ledger entry and notifies.
            $approvals->approve($payment->fresh(), auth()->id());

            AuditService::log('approve', 'payment', $payment->id, ['status' => 'pending'], ['status' => 'approved', 'method' => 'walk-in']);

            return $payment->fresh();
        });

        return redirect()
            ->route('cashier.payment.success', $payment->id)
            ->with('success', 'Payment recorded successfully.');
    }

    public function success($id)
    {
        $payment = Payment::with(['student.studentAccount', 'reviewer'])
            ->where('payment_method', 'cash')
            ->findOrFail($id);

        $remaining = $payment->student->studentAccount
            ? (float) $payment->student->studentAccount->outstanding_balance
            : 0.0;
        $previous = $remaining + (float) $payment->amount;

        return view('cashier.payment.success', compact('payment', 'previous', 'remaining'));
    }

    /**
     * Fees applicable to the student, loaded from existing assessment data:
     * the student's own financial charges first, falling back to the fee
     * catalog when the student has no charges yet.
     */
    protected function applicableFees(Student $student): array
    {
        $charges = $student->financialCharges()
            ->with('financialCategory')
            ->where('status', 'active')
            ->get();

        if ($charges->isNotEmpty()) {
            return $charges->map(function ($charge) {
                return [
                    'name' => $charge->financialCategory->name ?? $charge->description,
                    'amount' => (float) $charge->amount,
                ];
            })->values()->all();
        }

        return FeeAssessment::orderBy('fee_name')
            ->get(['fee_name', 'default_amount'])
            ->map(function ($fee) {
                return [
                    'name' => $fee->fee_name,
                    'amount' => (float) $fee->default_amount,
                ];
            })->values()->all();
    }

    /**
     * Generate a unique official receipt number (OR-YYYY-######).
     * Must be called inside a database transaction.
     */
    protected function generateReceiptNumber(): string
    {
        $year = now()->year;
        $prefix = 'OR-' . $year . '-';

        $last = Payment::where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $sequence = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Payment::where('reference_number', $candidate)->exists());

        return $candidate;
    }
}
