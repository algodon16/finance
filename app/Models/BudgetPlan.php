<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlan extends Model
{
    protected $fillable = [
        'budget_name', 'fiscal_year', 'department', 'budget_category',
        'allocated_amount', 'utilized_amount', 'start_date', 'end_date',
        'status', 'description', 'justification', 'funding_source',
        'supporting_document', 'rejection_reason', 'admin_remarks',
        'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by',
        'approved_by', 'approved_at', 'revision_number', 'cancelled_at', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'utilized_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetPlanItem::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Official = part of the books (submitted → approved/active). Drafts/rejected/cancelled are working copies. */
    public const OFFICIAL = ['submitted', 'under_review', 'approved', 'active'];

    public function isOfficial(): bool
    {
        return in_array($this->status, self::OFFICIAL, true);
    }

    /**
     * Find another official plan for the same department + fiscal year
     * (case-insensitive). Used to block duplicate budgets across roles.
     */
    public static function officialDuplicateExists(string $department, string $fiscalYear, ?int $ignoreId = null): ?self
    {
        $q = self::where('fiscal_year', $fiscalYear)
            ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim($department))])
            ->whereIn('status', self::OFFICIAL);
        if ($ignoreId) $q->where('id', '!=', $ignoreId);
        return $q->first();
    }

    /** Server-side: remaining = allocated - utilized. Never trust frontend. */
    public function getRemainingAmountAttribute(): string
    {
        return bcsub((string) $this->allocated_amount, (string) $this->utilized_amount, 2);
    }

    public function getUtilizationRateAttribute(): float
    {
        if ((float) $this->allocated_amount <= 0) return 0.0;
        return round(((float) $this->utilized_amount / (float) $this->allocated_amount) * 100, 2);
    }
}
