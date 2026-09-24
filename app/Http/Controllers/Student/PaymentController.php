<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\AuditService;
use App\Services\PaymentApprovalService;
use App\Services\ReferenceVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $student = auth()->user()->student;

        if (! $student) {
            abort(403, 'No student record linked to this account.');
        }

        $query = Payment::where('student_id', $student->id)
            ->with('paymentProofs');

        if ($request->filled('search')) {
            $query->where('reference_number', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('date')) {
            $query->whereDate('payment_date', $request->date);
        }

        $payments = $query->latest()->paginate(15)->withQueryString();

        return view('student.payments.index', compact('payments', 'student'));
    }

    public function create()
    {
        // Kept for backwards compatibility — the main form now lives on index.
        return redirect()->route('student.payments.index');
    }

    public function store(Request $request)
    {
        $student = auth()->user()->student;

        if (! $student) {
            abort(403, 'No student record linked to this account.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank_transfer,gcash,maya,other',
            'reference_number' => 'required|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:1000',
            'proof_of_payment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Server-side receipt verification: OCR the uploaded receipt and
        // compare against the entered reference number. Never approved
        // on mismatch; unreadable receipts go to manual cashier review.
        $check = $this->runReferenceCheck(
            $request->file('proof_of_payment'),
            null,
            $validated['reference_number']
        );

        if ($check['outcome'] !== 'proceed') {
            return back()->withErrors(['reference_number' => $check['message']])->withInput();
        }

        $payment = Payment::create([
            'student_id' => $student->id, // never trust browser input
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'],
            'payment_date' => $validated['payment_date'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            ...$this->matchAttributes($check['result']),
        ]);

        if ($request->hasFile('proof_of_payment')) {
            $files = $request->file('proof_of_payment');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                $path = $file->store('payment-proofs', 'public');
                PaymentProof::create([
                    'payment_id' => $payment->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'uploaded_at' => now(),
                ]);
            }
        }

        AuditService::log('create', 'payment', $payment->id, null, $payment->toArray());

        // Reference matched the receipt and all validations passed:
        // complete the same approval workflow the cashier performs.
        if ($check['result']['status'] === ReferenceVerificationService::MATCHED) {
            app(PaymentApprovalService::class)->approve($payment);

            AuditService::log('approve', 'payment', $payment->id, ['status' => 'pending'], ['status' => 'approved', 'method' => 'automatic']);

            return redirect()->route('student.payments.index')
                ->with('success', 'Payment verified successfully. Your payment has been automatically verified.');
        }

        return redirect()->route('student.payments.index')
            ->with('success', 'Payment submitted for manual review. We could not reliably read the reference number from the receipt.');
    }

    public function show($id)
    {
        $student = auth()->user()->student;
        $payment = Payment::where('student_id', $student->id)
            ->with(['paymentProofs', 'reviewer'])
            ->findOrFail($id);

        return view('student.payments.show', compact('payment'));
    }

    public function edit($id)
    {
        $student = auth()->user()->student;
        $payment = Payment::where('student_id', $student->id)
            ->with(['paymentProofs'])
            ->findOrFail($id);

        if (! in_array($payment->status, ['pending', 'rejected'])) {
            return redirect()->route('student.payments.index')
                ->with('error', 'This payment has already been verified and cannot be edited. Please contact the Cashier or Accounting Office.');
        }

        return view('student.payments.edit', compact('payment'));
    }

    public function update(Request $request, $id)
    {
        $student = auth()->user()->student;
        $payment = Payment::where('student_id', $student->id)
            ->with(['paymentProofs'])
            ->findOrFail($id);

        if (! in_array($payment->status, ['pending', 'rejected'])) {
            return redirect()->route('student.payments.index')
                ->with('error', 'This payment has already been verified and cannot be edited.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank_transfer,gcash,maya,other',
            'reference_number' => 'required|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:1000',
            'proof_of_payment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $oldValues = $payment->toArray();
        $wasRejected = $payment->status === 'rejected';

        // Re-verify server-side: a new upload is checked directly, while a
        // changed reference number is re-checked against the stored receipt.
        $newFile = $request->file('proof_of_payment');
        $newFile = is_array($newFile) ? ($newFile[0] ?? null) : $newFile;

        $storedPath = null;
        if (! $newFile) {
            $latestProof = $payment->paymentProofs()->latest('id')->first();
            if ($latestProof && Storage::disk('public')->exists($latestProof->file_path)) {
                $storedPath = Storage::disk('public')->path($latestProof->file_path);
            }
        }

        if ($newFile || $storedPath || $validated['reference_number'] !== $payment->reference_number) {
            $check = $this->runReferenceCheck(
                $newFile,
                $storedPath,
                $validated['reference_number'],
                $payment->id
            );

            if ($check['outcome'] !== 'proceed') {
                return back()->withErrors(['reference_number' => $check['message']])->withInput();
            }

            $matchAttributes = $this->matchAttributes($check['result']);
            $reverifiedMatched = $check['result']['status'] === ReferenceVerificationService::MATCHED;
        } else {
            $matchAttributes = [];
            $reverifiedMatched = false;
        }

        $payment->update([
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'],
            'payment_date' => $validated['payment_date'],
            'description' => $validated['description'] ?? null,
            // Rejected payments go back to pending for re-verification.
            // Pending payments remain pending.
            'status' => 'pending',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            ...$matchAttributes,
        ]);

        if ($request->hasFile('proof_of_payment')) {
            $files = $request->file('proof_of_payment');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                $path = $file->store('payment-proofs', 'public');
                PaymentProof::create([
                    'payment_id' => $payment->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'uploaded_at' => now(),
                ]);
            }
            // Remove replaced old proofs so only the newest file remains.
            $freshProofIds = $payment->paymentProofs()->latest('id')->limit(count($files))->pluck('id');
            $oldProofs = $payment->paymentProofs()->whereNotIn('id', $freshProofIds)->get();
            foreach ($oldProofs as $old) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }

        AuditService::log('update', 'payment', $payment->id, $oldValues, $payment->fresh()->toArray());

        if (! empty($reverifiedMatched)) {
            app(PaymentApprovalService::class)->approve($payment->fresh());

            AuditService::log('approve', 'payment', $payment->id, ['status' => 'pending'], ['status' => 'approved', 'method' => 'automatic']);

            return redirect()->route('student.payments.index')
                ->with('success', 'Payment verified successfully. Your payment has been automatically verified.');
        }

        $message = $wasRejected
            ? 'Payment updated and resubmitted for verification.'
            : 'Payment information updated successfully.';

        if (! empty($matchAttributes) && ($payment->fresh()->reference_match_status === ReferenceVerificationService::UNREADABLE)) {
            $message .= ' We could not reliably read the reference number; a cashier will review it.';
        }

        return redirect()->route('student.payments.index')->with('success', $message);
    }

    /**
     * Server-side receipt reference check. Runs OCR on the final submitted
     * values (never trusts frontend validation), then enforces the
     * duplicate-reference rule. MATCHED proceeds to automatic approval;
     * UNREADABLE proceeds to manual cashier review; MISMATCHED/DUPLICATE
     * reject the submission.
     */
    protected function runReferenceCheck(?UploadedFile $file, ?string $storedPath, string $reference, ?int $excludeId = null): array
    {
        $service = new ReferenceVerificationService();

        if ($file) {
            $result = $service->verifyUpload($file, $reference);
        } elseif ($storedPath) {
            $result = $service->verifyStoredFile($storedPath, $reference);
        } else {
            return ['outcome' => 'error', 'message' => 'Please upload your payment receipt.'];
        }

        if ($result['status'] === ReferenceVerificationService::MISMATCHED) {
            return ['outcome' => 'reject', 'message' => $result['message']];
        }

        if ($service->isDuplicateReference($reference, $excludeId)) {
            return ['outcome' => 'reject', 'message' => 'Payment was not accepted. This reference number has already been used.'];
        }

        return ['outcome' => 'proceed', 'result' => $result];
    }

    protected function matchAttributes(array $result): array
    {
        return [
            'reference_ocr_text' => $result['ocrText'] ?? null,
            'reference_ocr_result' => $result['extracted'] ?? null,
            'reference_match_status' => $result['status'],
            'verification_message' => $result['message'] ?? null,
            'verified_at' => now(),
        ];
    }

    /**
     * Serve proof files only to the owning student (no public guessing of URLs).
     */
    public function proof($id, $proofId)
    {
        $student = auth()->user()->student;
        $payment = Payment::where('student_id', $student->id)->findOrFail($id);
        $proof = PaymentProof::where('payment_id', $payment->id)->findOrFail($proofId);

        if (! Storage::disk('public')->exists($proof->file_path)) {
            abort(404, 'Proof file not found.');
        }

        return Storage::disk('public')->response($proof->file_path);
    }
}
