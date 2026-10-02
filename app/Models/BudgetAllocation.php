<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetAllocation extends Model
{
    protected $fillable = [
        'budget_plan_id', 'allocation_type', 'allocated_to',
        'amount', 'allocation_date', 'remarks', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
    ];

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    /** Human allocation reference, e.g. BA-2027-0001. */
    public function getAllocationIdAttribute(): string
    {
        $year = $this->allocation_date?->format('Y') ?? $this->created_at?->format('Y') ?? now()->format('Y');
        return 'BA-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
