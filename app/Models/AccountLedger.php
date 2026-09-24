<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountLedger extends Model
{
    protected $table = 'account_ledger';

    protected $fillable = [
        'student_id',
        'transaction_date',
        'reference_number',
        'description',
        'debit',
        'credit',
        'balance',
        'payment_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
