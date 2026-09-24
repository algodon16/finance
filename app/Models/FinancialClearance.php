<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialClearance extends Model
{
    protected $table = 'financial_clearance';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'semester_id',
        'status',
        'evaluated_at',
        'evaluated_by',
    ];

    protected $casts = [
        'evaluated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
