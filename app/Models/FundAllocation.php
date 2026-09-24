<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundAllocation extends Model
{
    protected $fillable = [
        'fund_id', 'budget_plan_id', 'allocated_to', 'amount',
        'allocation_date', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }
}
