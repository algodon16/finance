<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class ReportController extends Controller
{
    public function payments()
    {
        $payments = Payment::with(['student', 'reviewer'])
            ->latest()
            ->paginate(20);

        $totalCollected = Payment::where('status', 'approved')->sum('amount');
        $pendingCount = Payment::where('status', 'pending')->count();
        $approvedCount = Payment::where('status', 'approved')->count();
        $rejectedCount = Payment::where('status', 'rejected')->count();

        return view('admin.payments.index', compact(
            'payments',
            'totalCollected',
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }
}
