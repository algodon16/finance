<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentRule extends Model
{
    protected $fillable = [
        'program',
        'year_level',
        'semester',
        'academic_year',
    ];

    public function fees()
    {
        return $this->belongsToMany(
            FeeAssessment::class,
            'assessment_rule_fees',
            'assessment_rule_id',
            'fee_assessment_id'
        )->withPivot('amount')->withTimestamps();
    }

    public function totalAmount(): float
    {
        return (float) $this->fees()->sum('assessment_rule_fees.amount');
    }
}
