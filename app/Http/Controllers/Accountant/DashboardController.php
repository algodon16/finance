<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountLedger;
use App\Models\Payment;
use App\Models\StudentAccount;

class DashboardController extends Controller
{
    public function index()
    {
        // Financial records only: approved + posted payments.
        $recordedStatuses = ['approved', 'posted'];

        $totalCollections = (float) Payment::whereIn('status', $recordedStatuses)->sum('amount');

        $outstandingBalance = (float) StudentAccount::sum('outstanding_balance');

        $totalPayments = Payment::whereIn('status', $recordedStatuses)->count();

        // Unreconciled = recorded payments whose ledger credit does not match the system amount
        // (includes payments with no ledger entry at all).
        $recordedPayments = Payment::whereIn('status', $recordedStatuses)->get(['id', 'amount']);
        $ledgerCredits = AccountLedger::whereIn('payment_id', $recordedPayments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');

        $unreconciledCount = 0;
        foreach ($recordedPayments as $payment) {
            $actual = (float) ($ledgerCredits[$payment->id] ?? 0);
            $hasLedger = array_key_exists($payment->id, $ledgerCredits->toArray());
            if (! $hasLedger || abs($actual - (float) $payment->amount) > 0.009) {
                $unreconciledCount++;
            }
        }

        $recentTransactions = Payment::with('student')
            ->whereIn('status', $recordedStatuses)
            ->latest('payment_date')
            ->take(10)
            ->get();

        // Outstanding balances grouped by program (real assessment/payment data).
        $outstandingByProgram = StudentAccount::join('students', 'students.id', '=', 'student_accounts.student_id')
            ->groupBy('students.program')
            ->selectRaw('students.program as program, SUM(total_charges) as assessment, SUM(total_paid) as paid, SUM(outstanding_balance) as outstanding')
            ->orderByDesc('outstanding')
            ->get();

        // Recent reconciliation snapshot (system vs actual ledger records).
        $reconPayments = Payment::whereIn('status', $recordedStatuses)
            ->latest('payment_date')
            ->take(5)
            ->get(['id', 'amount', 'payment_date', 'created_at']);
        $reconCredits = AccountLedger::whereIn('payment_id', $reconPayments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');
        $reconMap = $reconCredits->toArray();

        $reconciliationSummary = $reconPayments->map(function ($payment) use ($reconMap) {
            $system = (float) $payment->amount;
            $hasLedger = array_key_exists($payment->id, $reconMap);
            $actual = $hasLedger ? (float) $reconMap[$payment->id] : 0.0;
            $difference = round($actual - $system, 2);

            return (object) [
                'date' => $payment->payment_date ?? optional($payment->created_at)->toDateString(),
                'system_amount' => $system,
                'actual_amount' => $actual,
                'difference' => $difference,
                'status' => (! $hasLedger || abs($difference) >= 0.01)
                    ? ($hasLedger ? 'Variance' : 'Unreconciled')
                    : 'Reconciled',
            ];
        });

        return view('accountant.dashboard', compact(
            'totalCollections',
            'outstandingBalance',
            'totalPayments',
            'unreconciledCount',
            'recentTransactions',
            'outstandingByProgram',
            'reconciliationSummary'
        ));
    }
}
