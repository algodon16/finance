<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'reference_number', 'expense_category', 'department', 'payee',
        'amount', 'expense_date', 'description', 'supporting_document',
        'approval_status', 'payment_status', 'budget_plan_id', 'fund_id',
        'created_by', 'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }
}
