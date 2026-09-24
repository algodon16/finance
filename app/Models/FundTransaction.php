<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransaction extends Model
{
    protected $fillable = [
        'fund_id', 'transaction_type', 'amount', 'transaction_date',
        'reference_number', 'description', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }
}
