<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementRequestItem extends Model
{
    protected $fillable = [
        'request_id',
        'item_id',
        'size',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function request()
    {
        return $this->belongsTo(ProcurementRequest::class, 'request_id');
    }

    public function item()
    {
        return $this->belongsTo(ProcurementItem::class, 'item_id');
    }
}
