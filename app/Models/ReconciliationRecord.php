<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRecord extends Model
{
    protected $fillable = [
        'reference_number', 'reconciliation_date', 'payment_method',
        'system_amount', 'actual_amount', 'variance', 'reference',
        'status', 'notes', 'supporting_document', 'payment_id',
        'prepared_by', 'reviewed_by', 'submitted_at', 'reviewed_at',
        'admin_remarks',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'system_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft', 'in_progress', 'reconciled', 'with_variance', 'submitted', 'reviewed',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Server-side variance = actual - system. Never trust frontend. */
    public function recalculateVariance(): void
    {
        $this->variance = round((float) $this->actual_amount - (float) $this->system_amount, 2);
        if (abs((float) $this->variance) < 0.01) {
            if (in_array($this->status, ['draft', 'in_progress', 'with_variance'], true)) {
                $this->status = 'reconciled';
            }
        } elseif (in_array($this->status, ['draft', 'in_progress', 'reconciled'], true)) {
            $this->status = 'with_variance';
        }
    }
}
