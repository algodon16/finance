<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsPayable extends Model
{
    protected $table = 'accounts_payable';

    protected $fillable = [
        'vendor', 'invoice_number', 'invoice_date', 'due_date',
        'amount', 'amount_paid', 'payment_schedule', 'payment_status',
        'supporting_document', 'remarks', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function payments()
    {
        return $this->hasMany(PayablePayment::class, 'accounts_payable_id');
    }

    /** Server-side remaining balance. */
    public function getRemainingBalanceAttribute(): string
    {
        return bcsub((string) $this->amount, (string) $this->amount_paid, 2);
    }

    /** Derive display status from dates + balances (server-side). */
    public function getDerivedStatusAttribute(): string
    {
        $remaining = (float) $this->remaining_balance;
        if ($remaining <= 0) return 'Fully Paid';
        $today = today();
        if ($this->due_date < $today) return 'Overdue';
        if ($this->due_date->diffInDays($today) <= 7) return 'Due Soon';
        if ((float) $this->amount_paid > 0) return 'Partially Paid';
        return 'Pending';
    }
}
