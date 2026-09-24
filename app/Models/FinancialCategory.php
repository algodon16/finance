<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function financialCharges()
    {
        return $this->hasMany(FinancialCharge::class);
    }
}
