<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSizeInventory extends Model
{
    protected $table = 'item_size_inventory';

    protected $fillable = [
        'item_id',
        'size',
        'quantity_available',
    ];

    protected $casts = [
        'quantity_available' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(ProcurementItem::class, 'item_id');
    }
}
