<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ItemSizeInventory;
use App\Models\ProcurementItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        // Catalog shows category-level items only; books live inside
        // the Learning Materials section (non-null category).
        $items = ProcurementItem::with('sizes')
            ->where('is_available', true)
            ->whereNull('category')
            ->orderBy('item_name')
            ->paginate(15);

        $recentRequests = ProcurementRequest::where('student_id', $student->id)
            ->with('items.item')
            ->latest()
            ->take(5)
            ->get();

        $materialsBundleId = ProcurementItem::where('item_name', 'Learning Materials')->value('id');

        return view('student.procurement.index', compact('items', 'recentRequests', 'materialsBundleId'));
    }

    public function show($id)
    {
        $item = ProcurementItem::with('sizes')->findOrFail($id);

        return view('student.procurement.show', compact('item'));
    }

    public function requestForm($id)
    {
        $item = ProcurementItem::findOrFail($id);
        $student = auth()->user()->student;

        return view('student.procurement.request-form', compact('item', 'student'));
    }

    public function submitRequest(Request $request)
    {
        $student = auth()->user()->student;

        $validated = $request->validate([
            'item_id' => 'required|exists:procurement_items,id',
            'size' => 'nullable|string|max:20',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:500',
        ]);

        $item = ProcurementItem::findOrFail($validated['item_id']);

        if (! $item->is_available) {
            return back()->withErrors(['quantity' => 'This item is not available.'])->withInput();
        }

        // Uniforms/sized items require a size; books and other
        // non-sized items never carry one.
        $size = isset($validated['size']) ? trim($validated['size']) : null;

        if ($item->isSized()) {
            if (empty($size)) {
                return back()->withErrors(['size' => 'Please select a size.'])->withInput();
            }
        } else {
            $size = null;
        }

        $procurementRequest = DB::transaction(function () use ($student, $item, $size, $validated) {
            if ($item->isSized()) {
                // Lock the size row so two students submitting at the same
                // time cannot over-request the same size.
                $sizeRow = ItemSizeInventory::where('item_id', $item->id)
                    ->where('size', $size)
                    ->lockForUpdate()
                    ->first();

                if (! $sizeRow || $sizeRow->quantity_available <= 0) {
                    throw ValidationException::withMessages([
                        'size' => 'The selected size is out of stock.',
                    ]);
                }

                if ($validated['quantity'] > $sizeRow->quantity_available) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Only ' . $sizeRow->quantity_available . ' piece(s) available for size ' . $sizeRow->size . '.',
                    ]);
                }

                $sizeRow->decrement('quantity_available', $validated['quantity']);

                $item->update([
                    'stock_quantity' => ItemSizeInventory::where('item_id', $item->id)->sum('quantity_available'),
                ]);
            } else {
                $lockedItem = ProcurementItem::whereKey($item->id)->lockForUpdate()->first();

                if ($lockedItem->stock_quantity < $validated['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Insufficient stock available.',
                    ]);
                }
                // Non-sized stock is deducted on approval (existing flow).
            }

            $subtotal = $item->price * $validated['quantity'];

            $requestModel = ProcurementRequest::create([
                'student_id' => $student->id,
                'status' => 'submitted',
                'total_amount' => $subtotal,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            ProcurementRequestItem::create([
                'request_id' => $requestModel->id,
                'item_id' => $item->id,
                'size' => $size,
                'quantity' => $validated['quantity'],
                'unit_price' => $item->price,
                'subtotal' => $subtotal,
            ]);

            return $requestModel;
        });

        AuditService::log('create', 'procurement_request', $procurementRequest->id, null, $procurementRequest->toArray());

        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if ($adminUser) {
            NotificationService::create(
                $adminUser->id,
                'New Procurement Request',
                'Student ' . $student->full_name . ' has submitted a procurement request.'
            );
        }

        return redirect()->route('student.procurement.request.show', $procurementRequest->id)
            ->with('success', 'Procurement request submitted successfully.');
    }

    public function showRequest($id)
    {
        $student = auth()->user()->student;
        $procurementRequest = ProcurementRequest::where('student_id', $student->id)
            ->with(['items.item', 'reviewer'])
            ->findOrFail($id);

        return view('student.procurement.request-show', compact('procurementRequest'));
    }
}
