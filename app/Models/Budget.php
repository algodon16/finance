<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'student_id',
        'allowance_frequency',
        'allowance_amount',
        'target_balance',
        'target_date',
    ];

    protected $casts = [
        'allowance_amount' => 'decimal:2',
        'target_balance' => 'decimal:2',
        'target_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
