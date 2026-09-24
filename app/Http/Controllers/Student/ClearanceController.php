<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\FinancialClearanceService;

class ClearanceController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        $clearance = FinancialClearanceService::getClearanceStatus($student->id);
        $studentAccount = $student->studentAccount;

        // Summary figures come from the existing student account record
        // (same source the clearance evaluation itself uses).
        $totalCharges = (float) ($studentAccount->total_charges ?? 0);
        $totalPayments = (float) ($studentAccount->total_paid ?? 0);
        $outstandingBalance = (float) ($studentAccount->outstanding_balance ?? 0);

        // Fee assessment rows from the existing financial charges.
        // Payments are not linked to individual charges in the schema, so
        // the approved-payment total is applied oldest-due-first purely for
        // per-row Paid/Balance display. Clearance evaluation itself still
        // uses outstanding_balance and is untouched.
        $charges = $student->financialCharges()
            ->with(['financialCategory', 'academicYear', 'semester'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $remaining = $totalPayments;
        foreach ($charges as $charge) {
            $amount = (float) $charge->amount;
            if ($charge->status === 'waived') {
                $charge->paid_amount = $amount;
                $charge->balance_amount = 0.0;
                continue;
            }
            $paid = min(max($remaining, 0.0), $amount);
            $charge->paid_amount = $paid;
            $charge->balance_amount = round($amount - $paid, 2);
            $remaining -= $paid;
        }

        $clearanceStatus = $clearance->status ?? $studentAccount->clearance_status ?? 'not_cleared';

        return view('student.clearance.index', compact(
            'student',
            'clearance',
            'clearanceStatus',
            'studentAccount',
            'totalCharges',
            'totalPayments',
            'outstandingBalance',
            'charges'
        ));
    }
}
