<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementItem extends Model
{
    protected $fillable = [
        'item_name',
        'description',
        'category',
        'price',
        'stock_quantity',
        'image_path',
        'is_available',
        'requires_size',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'requires_size' => 'boolean',
    ];

    public function procurementRequestItems()
    {
        return $this->hasMany(ProcurementRequestItem::class, 'item_id');
    }

    public function sizes()
    {
        return $this->hasMany(ItemSizeInventory::class, 'item_id')->orderBy('id');
    }

    public function isSized(): bool
    {
        return (bool) $this->requires_size;
    }

    /**
     * Sizes with stock remaining, for the request Size dropdown.
     */
    public function availableSizes()
    {
        return $this->sizes()->where('quantity_available', '>', 0)->get();
    }

    public function totalSizeStock(): int
    {
        return (int) $this->sizes()->sum('quantity_available');
    }

    /**
     * Keep the legacy total in sync with the per-size inventory.
     */
    public function syncStockFromSizes(): void
    {
        if ($this->isSized()) {
            $this->update(['stock_quantity' => $this->totalSizeStock()]);
        }
    }

    /**
     * Books shown ONLY inside the Learning Materials section.
     * General catalog items (uniforms, bundle card, ...) have NULL category.
     */
    public function scopeLearningMaterials($query)
    {
        return $query->whereNotNull('category');
    }

    public function isLearningMaterial(): bool
    {
        return ! is_null($this->category);
    }
}
