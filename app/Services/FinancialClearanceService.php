<?php

namespace App\Services;

use App\Models\FinancialClearance;
use App\Models\StudentAccount;
use App\Services\AuditService;

class FinancialClearanceService
{
    public static function evaluate($studentId, $academicYearId = null, $semesterId = null)
    {
        $studentAccount = StudentAccount::where('student_id', $studentId)->first();
        
        if (!$studentAccount) {
            return null;
        }
        
        $status = 'cleared';
        $pendingPayments = \App\Models\Payment::where('student_id', $studentId)
            ->whereIn('status', ['pending', 'under_review'])
            ->count();
        
        if ($pendingPayments > 0) {
            $status = 'pending_review';
        } elseif ($studentAccount->outstanding_balance > 0) {
            $status = 'not_cleared';
        }
        
        $clearance = FinancialClearance::updateOrCreate(
            [
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
            [
                'status' => $status,
                'evaluated_at' => now(),
                'evaluated_by' => auth()->id(),
            ]
        );
        
        $studentAccount->update(['clearance_status' => $status]);
        
        return $clearance;
    }
    
    public static function getClearanceStatus($studentId)
    {
        return FinancialClearance::where('student_id', $studentId)
            ->latest()
            ->first();
    }
}
