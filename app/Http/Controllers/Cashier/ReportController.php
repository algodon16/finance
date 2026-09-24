<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        $dailyCollections = Payment::where('status', 'approved')
            ->whereDate('reviewed_at', Carbon::today())
            ->sum('amount');

        $dailyApproved = Payment::where('status', 'approved')
            ->whereDate('reviewed_at', Carbon::today())
            ->count();

        $dailyRejected = Payment::where('status', 'rejected')
            ->whereDate('reviewed_at', Carbon::today())
            ->count();

        $dailyPending = Payment::where('status', 'pending')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $monthlyCollections = Payment::where('status', 'approved')
            ->whereMonth('reviewed_at', Carbon::now()->month)
            ->whereYear('reviewed_at', Carbon::now()->year)
            ->sum('amount');

        $monthlyApproved = Payment::where('status', 'approved')
            ->whereMonth('reviewed_at', Carbon::now()->month)
            ->whereYear('reviewed_at', Carbon::now()->year)
            ->count();

        $monthlyRejected = Payment::where('status', 'rejected')
            ->whereMonth('reviewed_at', Carbon::now()->month)
            ->whereYear('reviewed_at', Carbon::now()->year)
            ->count();

        $recentPayments = Payment::with('student')
            ->latest('reviewed_at')
            ->take(20)
            ->get();

        return view('cashier.reports.index', compact(
            'dailyCollections',
            'dailyApproved',
            'dailyRejected',
            'dailyPending',
            'monthlyCollections',
            'monthlyApproved',
            'monthlyRejected',
            'recentPayments'
        ));
    }

    public function dailyCollections()
    {
        $date = request('date', Carbon::today()->toDateString());

        $dailyCollections = Payment::whereDate('created_at', $date)
            ->where('status', 'approved')
            ->with('student')
            ->latest()
            ->paginate(20);

        $dailyTotal = Payment::whereDate('created_at', $date)
            ->where('status', 'approved')
            ->sum('amount');

        $dailyCount = Payment::whereDate('created_at', $date)
            ->where('status', 'approved')
            ->count();

        return view('cashier.reports.daily', compact('dailyCollections', 'dailyTotal', 'dailyCount', 'date'));
    }

    public function summary()
    {
        $dateFrom = request('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = request('date_to', Carbon::now()->toDateString());

        // Include the full end day so payments recorded today appear.
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $search = trim((string) request('search', ''));

        $verifications = Payment::whereBetween('created_at', [$from, $to])
            ->with('student')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'ilike', '%' . $search . '%')
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('first_name', 'ilike', '%' . $search . '%')
                                ->orWhere('last_name', 'ilike', '%' . $search . '%')
                                ->orWhere('student_number', 'ilike', '%' . $search . '%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pendingCount = Payment::where('status', 'pending')->whereBetween('created_at', [$from, $to])->count();
        $underReviewCount = Payment::where('status', 'under_review')->whereBetween('created_at', [$from, $to])->count();
        $approvedCount = Payment::where('status', 'approved')->whereBetween('created_at', [$from, $to])->count();
        $rejectedCount = Payment::where('status', 'rejected')->whereBetween('created_at', [$from, $to])->count();
        $totalApprovedAmount = Payment::where('status', 'approved')->whereBetween('created_at', [$from, $to])->sum('amount');
        $totalRejectedAmount = Payment::where('status', 'rejected')->whereBetween('created_at', [$from, $to])->sum('amount');

        return view('cashier.reports.summary', compact(
            'verifications', 'pendingCount', 'underReviewCount', 'approvedCount', 'rejectedCount',
            'totalApprovedAmount', 'totalRejectedAmount', 'dateFrom', 'dateTo'
        ));
    }
}
