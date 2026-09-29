<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPaymentAllocation extends Model
{
    protected $fillable = [
        'account_receivable_id', 'payment_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function receivable()
    {
        return $this->belongsTo(AccountReceivable::class, 'account_receivable_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
