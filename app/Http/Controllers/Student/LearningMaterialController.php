<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ProcurementItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class LearningMaterialController extends Controller
{
    private function cartKey(): string
    {
        return 'lm_cart_' . auth()->user()->student->id;
    }

    private function getCart(): array
    {
        return session()->get($this->cartKey(), []);
    }

    public function index()
    {
        $student = auth()->user()->student;

        $materials = ProcurementItem::learningMaterials()
            ->where('is_available', true)
            ->orderBy('item_name')
            ->get();

        // Rebuild cart lines with fresh item data; silently drop deleted items.
        $cartLines = [];
        $cartTotal = 0;
        foreach ($this->getCart() as $itemId => $qty) {
            $item = ProcurementItem::find($itemId);
            if (! $item) {
                continue;
            }
            $qty = max(1, (int) $qty);
            $subtotal = $item->price * $qty;
            $cartTotal += $subtotal;
            $cartLines[] = ['item' => $item, 'quantity' => $qty, 'subtotal' => $subtotal];
        }

        return view('student.procurement.materials.index', compact('materials', 'cartLines', 'cartTotal', 'student'));
    }

    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:procurement_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = ProcurementItem::learningMaterials()->findOrFail($validated['item_id']);

        if (! $item->is_available || $item->stock_quantity <= 0) {
            return back()->withErrors(['quantity' => 'This material is out of stock.']);
        }

        $cart = $this->getCart();
        $newQty = ($cart[$item->id] ?? 0) + $validated['quantity'];

        if ($newQty > $item->stock_quantity) {
            return back()->withErrors(['quantity' => 'Only ' . $item->stock_quantity . ' unit(s) available in stock.']);
        }

        $cart[$item->id] = $newQty;
        session()->put($this->cartKey(), $cart);

        return back()->with('success', $item->item_name . ' added to your request.');
    }

    public function removeFromCart(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|integer',
        ]);

        $cart = $this->getCart();
        unset($cart[$validated['item_id']]);
        session()->put($this->cartKey(), $cart);

        return back()->with('success', 'Material removed from your request.');
    }

    public function submit(Request $request)
    {
        $student = auth()->user()->student;
        $cart = $this->getCart();

        if (empty($cart)) {
            return back()->withErrors(['cart' => 'Please add at least one material before submitting.']);
        }

        // Re-validate every line against live stock; never trust session data.
        $lines = [];
        $total = 0;
        foreach ($cart as $itemId => $qty) {
            $item = ProcurementItem::learningMaterials()->find($itemId);
            if (! $item || ! $item->is_available || $item->stock_quantity <= 0) {
                return back()->withErrors(['cart' => 'A material in your request is no longer available.']);
            }
            $qty = max(1, (int) $qty);
            if ($qty > $item->stock_quantity) {
                return back()->withErrors(['cart' => $item->item_name . ': only ' . $item->stock_quantity . ' unit(s) available.']);
            }
            $subtotal = $item->price * $qty;
            $total += $subtotal;
            $lines[] = ['item' => $item, 'quantity' => $qty, 'subtotal' => $subtotal];
        }

        $procurementRequest = ProcurementRequest::create([
            'student_id' => $student->id,
            'status' => 'submitted',
            'total_amount' => $total,
            'remarks' => $request->input('remarks'),
        ]);

        foreach ($lines as $line) {
            ProcurementRequestItem::create([
                'request_id' => $procurementRequest->id,
                'item_id' => $line['item']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['item']->price,
                'subtotal' => $line['subtotal'],
            ]);
        }

        session()->forget($this->cartKey());

        AuditService::log('create', 'procurement_request', $procurementRequest->id, null, $procurementRequest->toArray());

        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if ($adminUser) {
            NotificationService::create(
                $adminUser->id,
                'New Procurement Request',
                'Student ' . $student->full_name . ' has submitted a learning materials request.'
            );
        }

        return redirect()->route('student.procurement.request.show', $procurementRequest->id)
            ->with('success', 'Learning materials request submitted successfully.');
    }
}
