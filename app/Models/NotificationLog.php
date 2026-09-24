<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'student_id',
        'financial_charge_id',
        'notification_type',
        'reminder_type',
        'recipient_email',
        'subject',
        'amount_due',
        'deadline',
        'sent_at',
        'delivery_status',
        'error_message',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'deadline' => 'date',
        'sent_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function charge()
    {
        return $this->belongsTo(FinancialCharge::class, 'financial_charge_id');
    }

    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }
}
