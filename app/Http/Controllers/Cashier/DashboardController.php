<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        // Collection overview (Collections module integrated here).
        $totalCollections = Payment::where('status', 'approved')->sum('amount');
        $totalTransactions = Payment::count();
        $pendingCount = Payment::where('status', 'pending')->count();
        $verifiedCount = Payment::where('status', 'approved')->count();

        // Recent transactions across all statuses for the dashboard table.
        $recentTransactions = Payment::with('student')
            ->latest()
            ->take(10)
            ->get();

        return view('cashier.dashboard', compact(
            'totalCollections',
            'totalTransactions',
            'pendingCount',
            'verifiedCount',
            'recentTransactions'
        ));
    }
}
