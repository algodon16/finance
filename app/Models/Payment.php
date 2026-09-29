<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'amount',
        'payment_method',
        'reference_number',
        'reference_ocr_text',
        'reference_ocr_result',
        'reference_match_status',
        'verification_message',
        'verified_at',
        'payment_date',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'admin_remarks',
        'submitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function paymentProofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function accountLedgerEntries()
    {
        return $this->hasMany(AccountLedger::class);
    }
}
