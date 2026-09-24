<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayablePayment extends Model
{
    protected $fillable = [
        'accounts_payable_id', 'amount', 'payment_date',
        'payment_method', 'reference_number', 'remarks', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function payable()
    {
        return $this->belongsTo(AccountsPayable::class, 'accounts_payable_id');
    }
}
