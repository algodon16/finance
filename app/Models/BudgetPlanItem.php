<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanItem extends Model
{
    protected $fillable = [
        'budget_plan_id', 'item_name', 'category', 'quantity',
        'unit_cost', 'line_total', 'justification', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /** Server-side line total = qty × unit cost. */
    public static function computeTotal($qty, $unit): string
    {
        return bcmul((string) $qty, (string) $unit, 2);
    }
}
