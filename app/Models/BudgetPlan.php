<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlan extends Model
{
    protected $fillable = [
        'budget_name', 'fiscal_year', 'department', 'budget_category',
        'allocated_amount', 'utilized_amount', 'start_date', 'end_date',
        'status', 'description', 'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'utilized_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class);
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
