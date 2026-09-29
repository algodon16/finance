<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierItem extends Model
{
    protected $fillable = ['supplier_id', 'item_name', 'category', 'last_unit_cost', 'is_active'];

    protected $casts = ['last_unit_cost' => 'decimal:2', 'is_active' => 'boolean'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * I-record ang item sa catalog ng supplier (mula sa budget item row).
     * Ina-update ang category at huling presyo kapag nandiyan na.
     */
    public static function record(string $supplierName, string $itemName, ?string $category = null, $unitCost = null): ?self
    {
        $item = trim(preg_replace('/\s+/', ' ', $itemName));
        if ($item === '') return null;
        $sup = Supplier::findOrAdd($supplierName);
        if (! $sup) return null;
        $existing = static::where('supplier_id', $sup->id)->get()
            ->first(fn($r) => mb_strtolower($r->item_name) === mb_strtolower($item));
        $data = ['category' => $category ?: ($existing->category ?? null), 'last_unit_cost' => $unitCost !== null && $unitCost !== '' ? (float) $unitCost : ($existing->last_unit_cost ?? null)];
        if ($existing) {
            $existing->update($data);
            return $existing->fresh();
        }
        return static::create(['supplier_id' => $sup->id, 'item_name' => $item] + $data);
    }
}
