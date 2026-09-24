<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAccount extends Model
{
    protected $fillable = [
        'student_id',
        'total_charges',
        'total_paid',
        'outstanding_balance',
        'clearance_status',
    ];

    protected $casts = [
        'total_charges' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getIsClearedAttribute()
    {
        return $this->clearance_status === 'cleared';
    }
}
