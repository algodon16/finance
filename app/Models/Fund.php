<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $fillable = [
        'fund_name', 'fund_source', 'fund_type', 'initial_balance',
        'current_balance', 'reserved_amount', 'description', 'status', 'created_by',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'reserved_amount' => 'decimal:2',
    ];

    public function allocations()
    {
        return $this->hasMany(FundAllocation::class);
    }

    public function transactions()
    {
        return $this->hasMany(FundTransaction::class);
    }

    /** Server-side: used = initial - current - reserved. */
    public function getUsedAmountAttribute(): string
    {
        return bcsub(bcsub((string) $this->initial_balance, (string) $this->current_balance, 2), (string) $this->reserved_amount, 2);
    }

    public function getAvailableAmountAttribute(): string
    {
        return bcsub((string) $this->current_balance, (string) $this->reserved_amount, 2);
    }
}
