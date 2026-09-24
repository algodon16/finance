<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FinancialCharge;
use App\Models\Semester;
use App\Models\StudentAccount;
use App\Models\Payment;
use App\Models\Notification;
use App\Services\NotificationService;

class DashboardController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        if (!$student) {
            abort(403, 'No student record found for this account.');
        }

        $studentAccount = StudentAccount::where('student_id', $student->id)->first();

        // Official totals come from the student's own account record, which is
        // maintained by the charge and payment-approval workflow. Fall back to
        // live database sums when no account record exists yet.
        $totalCharges = $studentAccount
            ? (float) $studentAccount->total_charges
            : (float) FinancialCharge::where('student_id', $student->id)
                ->where('status', '!=', 'waived')
                ->sum('amount');

        // Only approved payments reduce the official balance.
        $totalPaid = $studentAccount
            ? (float) $studentAccount->total_paid
            : (float) Payment::where('student_id', $student->id)
                ->where('status', 'approved')
                ->sum('amount');

        $outstandingBalance = $studentAccount
            ? (float) $studentAccount->outstanding_balance
            : max(0, $totalCharges - $totalPaid);

        // The dashboard's "Recent Transactions" table is fed by the student's
        // own financial charges (description / amount / status / created_at).
        $recentCharges = FinancialCharge::where('student_id', $student->id)
            ->with('financialCategory')
            ->latest()
            ->take(5)
            ->get();

        $recentTransactions = $recentCharges;

        $recentPayments = Payment::where('student_id', $student->id)
            ->latest()
            ->take(5)
            ->get();

        $unreadNotifications = Notification::where('user_id', auth()->id())
            ->unread()
            ->count();

        $pendingPayments = Payment::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->count();

        // Active academic term for the dashboard header (no session values exist
        // in this project, so the active DB records are the source of truth).
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::latest('id')->first();
        $semester = Semester::where('is_active', true)->first()
            ?? Semester::latest('id')->first();

        // Aliases used by the redesigned dashboard blade (same data, no extra queries).
        $totalBalance = $totalCharges;
        $recentPaymentSubmissions = $recentPayments;
        $clearanceStatus = ($studentAccount && $studentAccount->is_cleared) ? 'cleared' : 'not_cleared';

        return view('student.dashboard', compact(
            'student',
            'studentAccount',
            'totalCharges',
            'totalPaid',
            'outstandingBalance',
            'recentCharges',
            'recentTransactions',
            'recentPayments',
            'unreadNotifications',
            'pendingPayments',
            'totalBalance',
            'recentPaymentSubmissions',
            'clearanceStatus',
            'academicYear',
            'semester'
        ));
    }
}
