<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function financialCharges()
    {
        return $this->hasMany(FinancialCharge::class);
    }

    public function financialClearances()
    {
        return $this->hasMany(FinancialClearance::class);
    }
}
