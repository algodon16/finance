<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemSizeInventory;
use App\Models\ProcurementItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcurementItemController extends Controller
{
    public function index()
    {
        $items = ProcurementItem::with('sizes')->latest()->paginate(20);

        return view('admin.procurement-items.index', compact('items'));
    }

    public function create()
    {
        return view('admin.procurement-items.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'stock_quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_available' => 'boolean',
            'requires_size' => 'boolean',
            'sizes' => 'nullable|array',
            'sizes.*.size' => 'required_with:sizes|string|max:20',
            'sizes.*.quantity' => 'required_with:sizes|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('procurement-items', 'public');
        }

        $item = DB::transaction(function () use ($validated, $imagePath) {
            $requiresSize = ! empty($validated['requires_size']);

            $item = ProcurementItem::create([
                'item_name' => $validated['item_name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock_quantity' => $requiresSize ? 0 : $validated['stock_quantity'],
                'image_path' => $imagePath,
                'is_available' => $validated['is_available'] ?? true,
                'requires_size' => $requiresSize,
            ]);

            if ($requiresSize) {
                $this->syncSizes($item, $validated['sizes'] ?? []);
                $item->update([
                    'stock_quantity' => $item->totalSizeStock(),
                ]);
            }

            return $item;
        });

        AuditService::log('create', 'procurement_item', $item->id, null, $item->toArray());

        return redirect()->route('admin.procurementItems.index')
            ->with('success', 'Item created successfully.');
    }

    public function edit($id)
    {
        $item = ProcurementItem::with('sizes')->findOrFail($id);

        return view('admin.procurement-items.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = ProcurementItem::with('sizes')->findOrFail($id);

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'stock_quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_available' => 'boolean',
            'requires_size' => 'boolean',
            'sizes' => 'nullable|array',
            'sizes.*.size' => 'required_with:sizes|string|max:20',
            'sizes.*.quantity' => 'required_with:sizes|integer|min:0',
        ]);

        $oldData = $item->toArray();

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('procurement-items', 'public');
        }

        DB::transaction(function () use ($item, $validated) {
            $requiresSize = ! empty($validated['requires_size']);

            $item->update([
                'item_name' => $validated['item_name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock_quantity' => $requiresSize ? $item->stock_quantity : $validated['stock_quantity'],
                'image_path' => $validated['image_path'] ?? $item->image_path,
                'is_available' => $validated['is_available'] ?? true,
                'requires_size' => $requiresSize,
            ]);

            if ($requiresSize) {
                $this->syncSizes($item, $validated['sizes'] ?? []);
                $item->update([
                    'stock_quantity' => $item->totalSizeStock(),
                ]);
            } else {
                $item->sizes()->delete();
            }
        });

        AuditService::log('update', 'procurement_item', $item->id, $oldData, $item->fresh()->toArray());

        return redirect()->route('admin.procurementItems.index')
            ->with('success', 'Item updated successfully.');
    }

    /**
     * Sync per-size inventory rows from the item form.
     */
    protected function syncSizes(ProcurementItem $item, array $sizes): void
    {
        $seen = [];

        foreach ($sizes as $row) {
            $size = strtoupper(trim($row['size'] ?? ''));
            if ($size === '') {
                continue;
            }
            $seen[] = $size;

            ItemSizeInventory::updateOrCreate(
                ['item_id' => $item->id, 'size' => $size],
                ['quantity_available' => max(0, (int) ($row['quantity'] ?? 0))]
            );
        }

        if (! empty($seen)) {
            $item->sizes()->whereNotIn('size', $seen)->delete();
        }
    }

    public function destroy($id)
    {
        $item = ProcurementItem::findOrFail($id);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        AuditService::log('delete', 'procurement_item', $item->id, $item->toArray(), null);

        $item->delete();

        return redirect()->route('admin.procurementItems.index')
            ->with('success', 'Item deleted successfully.');
    }
}
