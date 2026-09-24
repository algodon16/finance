<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;

class PaymentVerificationController extends Controller
{
    public function index()
    {
        $query = Payment::with(['student', 'paymentProofs']);

        if (request()->filled('status') && in_array(request('status'), ['pending', 'under_review', 'approved', 'rejected'])) {
            $query->where('status', request('status'));
        }

        $payments = $query->latest()->paginate(20);

        return view('cashier.payments.index', compact('payments'));
    }

    public function show($id)
    {
        $payment = Payment::with(['student.studentAccount', 'paymentProofs', 'reviewer'])
            ->findOrFail($id);

        return view('cashier.payments.show', compact('payment'));
    }

    public function approve($id, PaymentApprovalService $approvals)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'This payment has already been processed.');
        }

        $approvals->approve($payment, auth()->id());

        AuditService::log('approve', 'payment', $payment->id, ['status' => 'pending'], ['status' => 'approved']);

        return redirect()->route('cashier.payments.index')
            ->with('success', 'Payment approved successfully.');
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'This payment has already been processed.');
        }

        $oldStatus = $payment->status;

        $payment->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        AuditService::log('reject', 'payment', $payment->id, ['status' => $oldStatus], ['status' => 'rejected', 'reason' => $validated['rejection_reason']]);

        NotificationService::create(
            $payment->student->user_id,
            'Payment Rejected',
            'Your payment of ₱' . number_format($payment->amount, 2) . ' has been rejected. Reason: ' . $validated['rejection_reason']
        );

        return redirect()->route('cashier.payments.show', $id)
            ->with('success', 'Payment rejected successfully.');
    }
}
