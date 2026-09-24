<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeAssessment extends Model
{
    protected $fillable = [
        'fee_name',
        'description',
        'default_amount',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
    ];

    public function assessmentRules()
    {
        return $this->belongsToMany(
            AssessmentRule::class,
            'assessment_rule_fees',
            'fee_assessment_id',
            'assessment_rule_id'
        )->withPivot('amount')->withTimestamps();
    }
}
