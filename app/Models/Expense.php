<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const PAYMENT_METHODS = ['Bank Transfer', 'Check', 'Cash', 'Other'];

    protected $fillable = [
        'reference_number', 'expense_category', 'department', 'payee',
        'amount', 'expense_date', 'proposed_payment_date', 'description',
        'justification', 'supporting_document',
        'approval_status', 'payment_status', 'budget_plan_id', 'fund_id', 'fund_source',
        'fund_allocation_id', 'related_payable_id',
        'payment_date', 'payment_method', 'payment_reference', 'proof_of_payment',
        'paid_by', 'paid_at',
        'rejection_reason', 'admin_remarks', 'submitted_at', 'submitted_by',
        'reviewed_at', 'reviewed_by', 'approved_at', 'revision_number', 'cancelled_at',
        'created_by', 'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'proposed_payment_date' => 'date',
        'payment_date' => 'date',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function allocation()
    {
        return $this->belongsTo(FundAllocation::class, 'fund_allocation_id');
    }

    public function relatedPayable()
    {
        return $this->belongsTo(AccountsPayable::class, 'related_payable_id');
    }

    /** Alias used by disbursement views. */
    public function sourcePayable()
    {
        return $this->belongsTo(AccountsPayable::class, 'related_payable_id');
    }

    /**
     * Financial-processing stage for disbursements linked to an approved
     * Financial Request (Original Request → AP → Disbursement chain).
     * Returns null for records outside that flow.
     *
     * FOR PROCESSING → PROCESSING → RECORDED → COMPLETED
     */
    public function getProcessingStageAttribute(): ?string
    {
        $ap = $this->relationLoaded('sourcePayable') ? $this->getRelation('sourcePayable') : $this->sourcePayable;
        if (! $ap || ! $ap->financial_request_id) return null;
        $fr = $ap->relationLoaded('financialRequest') ? $ap->getRelation('financialRequest') : $ap->financialRequest;
        if ($this->payment_status === 'paid' || ($fr && $fr->status === 'completed')) return 'COMPLETED';
        $actual = $fr ? $fr->actual_amount : null;
        $paid = (float) $ap->amount_paid;
        $docs = (bool) ($this->supporting_document || $this->proof_of_payment || $ap->supporting_document);
        if ($actual && $paid + 0.009 >= $actual && $docs) return 'RECORDED';
        if ($actual || $paid > 0) return 'PROCESSING';
        return 'FOR PROCESSING';
    }

    /** Manual proposals that were used as an AP source (AP created from this expense). */
    public function payables()
    {
        return $this->hasMany(AccountsPayable::class, 'expense_id');
    }

    /** Standalone/manual records (not auto-generated from an AP). */
    public function scopeManual($q)
    {
        return $q->whereNull('related_payable_id');
    }

    /** Auto-generated disbursements (exactly one per approved AP). */
    public function scopeAutoDisbursement($q)
    {
        return $q->whereNotNull('related_payable_id');
    }

    /**
     * Financially active records — each peso counted ONCE.
     * Manual proposals superseded by an approved AP are excluded
     * (their disbursement record carries the amount instead).
     */
    public function scopeFinanciallyActive($q)
    {
        return $q->where(function ($w) {
            $w->whereNotNull('related_payable_id')
              ->orWhereNotExists(function ($sq) {
                  $sq->selectRaw('1')->from('accounts_payable')
                     ->whereColumn('accounts_payable.expense_id', 'expenses.id')
                     ->where('accounts_payable.approval_status', 'approved');
              });
        });
    }

    public function getIsAutoAttribute(): bool
    {
        return $this->related_payable_id !== null;
    }

    public function getOriginLabelAttribute(): string
    {
        return $this->is_auto ? 'Auto from AP' : 'Manual Proposal';
    }
}
