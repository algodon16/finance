<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialCharge extends Model
{
    protected $fillable = [
        'student_id',
        'financial_category_id',
        'description',
        'amount',
        'due_date',
        'academic_year_id',
        'semester_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function financialCategory()
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
