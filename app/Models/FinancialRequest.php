<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialRequest extends Model
{
    protected $fillable = [
        'request_number', 'request_type', 'reference_type', 'reference_id',
        'budget_plan_id',
        'description', 'amount', 'department', 'request_date', 'prepared_by', 'student_id',
        'metadata', 'supporting_document', 'status', 'admin_decision',
        'admin_remarks', 'decided_by', 'decided_at', 'submitted_at',
        'submitted_by', 'reviewed_by', 'revision_number', 'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'request_date' => 'date',
        'decided_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public const TYPES = [
        'budget', 'fund_allocation', 'expense', 'disbursement',
        'payable', 'assessment_adjustment', 'reconciliation', 'other',
    ];

    public const STATUSES = [
        'draft', 'submitted', 'under_review', 'approved',
        'rejected', 'for_revision', 'completed',
    ];

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }
}
