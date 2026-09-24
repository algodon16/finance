<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\Payment;
use App\Models\AccountLedger;
use App\Models\FinancialCharge;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function studentStatement($studentId)
    {
        $student = Student::with('studentAccount')->findOrFail($studentId);

        $charges = $student->financialCharges()
            ->with('financialCategory')
            ->latest()
            ->get();

        $payments = $student->payments()
            ->with('reviewer')
            ->latest()
            ->get();

        $ledger = $student->accountLedger()
            ->latest('transaction_date')
            ->get();

        $totalCharges = $charges->sum('amount');
        $totalPayments = $payments->where('status', 'approved')->sum('amount');
        $outstandingBalance = $student->studentAccount ? $student->studentAccount->outstanding_balance : 0;

        return view('reports.student-statement', compact(
            'student',
            'charges',
            'payments',
            'ledger',
            'totalCharges',
            'totalPayments',
            'outstandingBalance'
        ));
    }

    public function paymentHistory($studentId)
    {
        $student = Student::findOrFail($studentId);

        $payments = Payment::where('student_id', $studentId)
            ->with('paymentProofs', 'reviewer')
            ->latest()
            ->paginate(20);

        return view('reports.payment-history', compact('student', 'payments'));
    }

    public function collectionSummary()
    {
        $monthlyCollections = Payment::where('status', 'approved')
            ->selectRaw('MONTH(reviewed_at) as month, YEAR(reviewed_at) as year, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $totalCollections = Payment::where('status', 'approved')->sum('amount');
        $totalApproved = Payment::where('status', 'approved')->count();
        $totalRejected = Payment::where('status', 'rejected')->count();
        $totalPending = Payment::where('status', 'pending')->count();

        return view('reports.collection-summary', compact(
            'monthlyCollections',
            'totalCollections',
            'totalApproved',
            'totalRejected',
            'totalPending'
        ));
    }

    public function accountsReceivable()
    {
        $accounts = StudentAccount::with('student')
            ->orderByDesc('outstanding_balance')
            ->paginate(20);

        $totalReceivables = StudentAccount::sum('outstanding_balance');
        $totalCharges = StudentAccount::sum('total_charges');
        $totalCollected = StudentAccount::sum('total_paid');

        return view('reports.accounts-receivable', compact(
            'accounts',
            'totalReceivables',
            'totalCharges',
            'totalCollected'
        ));
    }
}
