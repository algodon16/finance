<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'program',
        'year_level',
        'section',
        'contact_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studentAccount()
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function financialCharges()
    {
        return $this->hasMany(FinancialCharge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function accountLedger()
    {
        return $this->hasMany(AccountLedger::class);
    }

    public function budget()
    {
        return $this->hasOne(Budget::class);
    }

    public function aiPaymentRecommendations()
    {
        return $this->hasMany(AiPaymentRecommendation::class);
    }

    public function procurementRequests()
    {
        return $this->hasMany(ProcurementRequest::class);
    }

    public function financialClearances()
    {
        return $this->hasMany(FinancialClearance::class);
    }

    public function getFullNameAttribute()
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }
}
