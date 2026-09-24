<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentRecordController extends Controller
{
    /**
     * View-only list of recorded/posted financial payment transactions.
     * All filtering is done in the database (no JS-only filtering).
     */
    public function index(Request $request)
    {
        $query = Payment::with('student');

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_for')) {
            $query->where('description', 'like', '%' . $request->payment_for . '%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('student_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $payments = $query->latest('payment_date')->paginate(20)->withQueryString();

        $paymentMethods = ['cash', 'bank_transfer', 'gcash', 'maya', 'other'];
        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'posted', 'cancelled'];

        $paymentForOptions = Payment::whereNotNull('description')
            ->where('description', '!=', '')
            ->distinct()
            ->orderBy('description')
            ->pluck('description');

        return view('accountant.payment-records.index', compact(
            'payments',
            'paymentMethods',
            'statuses',
            'paymentForOptions'
        ));
    }

    /**
     * Read-only financial review of a single recorded payment.
     */
    public function show($id)
    {
        $payment = Payment::with(['student.studentAccount', 'paymentProofs', 'reviewer', 'accountLedgerEntries'])
            ->findOrFail($id);

        return view('accountant.payment-records.show', compact('payment'));
    }
}
