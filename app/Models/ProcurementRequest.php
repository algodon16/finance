<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementRequest extends Model
{
    protected $fillable = [
        'student_id',
        'request_number',
        'requesting_department',
        'item_description',
        'quantity',
        'estimated_cost',
        'supplier',
        'justification',
        'supporting_document',
        'status',
        'total_amount',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items()
    {
        return $this->hasMany(ProcurementRequestItem::class, 'request_id');
    }
}
