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
}
