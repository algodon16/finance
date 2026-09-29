<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'asset_code', 'asset_name', 'asset_category', 'serial_number',
        'acquisition_date', 'acquisition_cost', 'salvage_value',
        'useful_life_years', 'location', 'department', 'custodian',
        'asset_status', 'approval_status', 'rejection_reason', 'admin_remarks',
        'submitted_at', 'submitted_by', 'reviewed_at', 'reviewed_by',
        'approved_by', 'approved_at', 'revision_number', 'cancelled_at',
        'supporting_document', 'remarks', 'created_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Straight-line: annual = (cost - salvage) / useful life. Server-side only. */
    public function getAnnualDepreciationAttribute(): string
    {
        $life = max(1, (int) $this->useful_life_years);
        return bcdiv(bcsub((string) $this->acquisition_cost, (string) $this->salvage_value, 2), (string) $life, 2);
    }

    public function getMonthlyDepreciationAttribute(): string
    {
        return number_format((float) $this->annual_depreciation / 12, 2, '.', '');
    }

    public function getElapsedYearsAttribute(): float
    {
        $elapsed = $this->acquisition_date->diffInMonths(now()) / 12;
        return round(min($elapsed, (float) $this->useful_life_years), 2);
    }

    public function getAccumulatedDepreciationAttribute(): string
    {
        $acc = bcmul($this->annual_depreciation, (string) $this->elapsed_years, 2);
        $max = bcsub((string) $this->acquisition_cost, (string) $this->salvage_value, 2);
        return bccomp($acc, $max, 2) > 0 ? $max : $acc;
    }

    public function getBookValueAttribute(): string
    {
        return bcsub((string) $this->acquisition_cost, $this->accumulated_depreciation, 2);
    }

    public function getRemainingLifeAttribute(): float
    {
        return round(max(0, (float) $this->useful_life_years - $this->elapsed_years), 2);
    }
}
