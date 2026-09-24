<?php

namespace App\Services;

use App\Models\AccountLedger;
use App\Models\Payment;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;

/**
 * Single home for payment approval side effects (account balances,
 * ledger entry, clearance evaluation, student notification).
 * Used by both manual cashier approval and automatic verification
 * so the two paths can never diverge.
 */
class PaymentApprovalService
{
    public function approve(Payment $payment, ?int $reviewerId = null): Payment
    {
        return DB::transaction(function () use ($payment, $reviewerId) {
            $payment->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            $studentAccount = StudentAccount::where('student_id', $payment->student_id)->first();

            if ($studentAccount) {
                $studentAccount->update([
                    'total_paid' => $studentAccount->total_paid + $payment->amount,
                    'outstanding_balance' => max(0, $studentAccount->outstanding_balance - $payment->amount),
                ]);
            }

            $lastLedger = AccountLedger::where('student_id', $payment->student_id)
                ->latest('transaction_date')
                ->first();

            $runningBalance = $lastLedger ? $lastLedger->balance - $payment->amount : -$payment->amount;

            AccountLedger::create([
                'student_id' => $payment->student_id,
                'transaction_date' => now(),
                'reference_number' => $payment->reference_number,
                'description' => 'Payment received - ' . ucfirst(str_replace('_', ' ', $payment->payment_method)),
                'debit' => 0,
                'credit' => $payment->amount,
                'balance' => max(0, $runningBalance),
                'payment_id' => $payment->id,
            ]);

            FinancialClearanceService::evaluate($payment->student_id);

            NotificationService::create(
                $payment->student->user_id,
                'Payment Approved',
                'Your payment of ₱' . number_format($payment->amount, 2) . ' has been approved.'
            );

            return $payment->fresh();
        });
    }
}
