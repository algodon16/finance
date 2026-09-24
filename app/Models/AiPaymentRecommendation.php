<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPaymentRecommendation extends Model
{
    protected $fillable = [
        'student_id',
        'allowance_frequency',
        'allowance_amount',
        'recommended_amount',
        'recommended_date',
        'explanation',
    ];

    protected $casts = [
        'allowance_amount' => 'decimal:2',
        'recommended_amount' => 'decimal:2',
        'recommended_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
