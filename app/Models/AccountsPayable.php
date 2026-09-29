<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsPayable extends Model
{
    protected $table = 'accounts_payable';

    public const PAYMENT_TERMS = ['COD', '15 Days', '30 Days', '60 Days', 'Custom'];

    protected $fillable = [
        'vendor', 'invoice_number', 'invoice_date', 'due_date',
        'amount', 'amount_paid', 'payment_schedule', 'payment_terms', 'payment_status',
        'approval_status', 'category', 'description', 'budget_plan_id', 'fund_id',
        'expense_id', 'financial_request_id', 'fund_allocation_id',
        'supporting_document', 'remarks', 'rejection_reason', 'admin_remarks',
        'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by',
        'approved_by', 'approved_at', 'revision_number', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
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

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function financialRequest()
    {
        return $this->belongsTo(FinancialRequest::class);
    }

    public function allocation()
    {
        return $this->belongsTo(FundAllocation::class, 'fund_allocation_id');
    }

    public function payments()
    {
        return $this->hasMany(PayablePayment::class, 'accounts_payable_id');
    }

    /** Auto-generated disbursement (at most one — unique FK). */
    public function disbursement()
    {
        return $this->hasOne(Expense::class, 'related_payable_id');
    }

    /** Display reference: AP-2026-00001 (derived from id + year, no extra column). */
    public function getApNumberAttribute(): string
    {
        $year = optional($this->created_at)->format('Y') ?? date('Y');
        return 'AP-'.$year.'-'.str_pad((string) ($this->id ?? 0), 5, '0', STR_PAD_LEFT);
    }

    /** Unified disbursement stage across the AP → Expense chain. */
    public function getPaymentStageAttribute(): string
    {
        if (in_array($this->approval_status, ['rejected'], true)) return 'Rejected';
        if (in_array($this->approval_status, ['cancelled'], true)) return 'Cancelled';
        if ((float) $this->remaining_balance <= 0 && (float) $this->amount > 0) return 'Paid';
        if ((float) $this->amount_paid > 0) return 'Partially Paid';
        if (($this->approval_status ?? 'draft') === 'approved') return 'For Disbursement';
        if (in_array($this->approval_status, ['submitted', 'under_review'], true)) return 'Pending Approval';
        return 'Draft';
    }

    /**
     * Unified source transaction: approved Expense preferred,
     * otherwise approved Financial Request. No duplicated data —
     * vendor / department / amounts resolve through this relation.
     */
    public function source(): ?Model
    {
        if ($this->relationLoaded('expense') || $this->expense_id) {
            $e = $this->relationLoaded('expense') ? $this->getRelation('expense') : $this->expense;
            if ($e) return $e;
        }
        if ($this->relationLoaded('financialRequest') || $this->financial_request_id) {
            $f = $this->relationLoaded('financialRequest') ? $this->getRelation('financialRequest') : $this->financialRequest;
            if ($f) return $f;
        }
        return null;
    }

    public function getSourceKindAttribute(): ?string
    {
        if ($this->expense_id) return 'expense';
        if ($this->financial_request_id) return 'financial_request';
        return null;
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->expense) return $this->expense->reference_number.' — '.str($this->expense->description ?? $this->expense->expense_category)->limit(45);
        if ($this->financialRequest) return $this->financialRequest->request_number.' — '.str($this->financialRequest->description)->limit(45);
        return '—';
    }

    public function getSourceVendorAttribute(): string
    {
        if ($this->expense) return (string) ($this->expense->payee ?: '—');
        if ($this->financialRequest) {
            $items = $this->financialRequest->metadata['items'] ?? [];
            $suppliers = collect($items)->pluck('supplier')->filter()->unique()->values();
            return $suppliers->isNotEmpty() ? $suppliers->join(', ') : '—';
        }
        return (string) ($this->vendor ?: '—');
    }

    public function getSourceDepartmentAttribute(): string
    {
        if ($this->expense) return (string) ($this->expense->department ?: '—');
        if ($this->financialRequest) return (string) ($this->financialRequest->department ?: '—');
        return '—';
    }

    public function getSourceAmountAttribute(): ?string
    {
        if ($this->expense) return (string) $this->expense->amount;
        if ($this->financialRequest) return (string) $this->financialRequest->amount;
        return null;
    }

    /** Server-side remaining balance. */
    public function getRemainingBalanceAttribute(): string
    {
        return bcsub((string) $this->amount, (string) $this->amount_paid, 2);
    }

    /** Derive display status from dates + balances (server-side). */
    public function getDerivedStatusAttribute(): string
    {
        $remaining = (float) $this->remaining_balance;
        if ($remaining <= 0) return 'Fully Paid';
        $today = today();
        if ($this->due_date < $today) return 'Overdue';
        if ($this->due_date->diffInDays($today) <= 7) return 'Due Soon';
        if ((float) $this->amount_paid > 0) return 'Partially Paid';
        return 'Pending';
    }
}
