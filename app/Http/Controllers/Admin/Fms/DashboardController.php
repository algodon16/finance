<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\Asset;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Payment;
use App\Models\ProcurementRequest;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // All values computed server-side from real database records.
        $totalRevenue = (float) Payment::whereIn('status', ['approved', 'verified'])
            ->orWhere('verification_status', 'reconciled')->sum('amount');
        // Correct the OR precedence: recompute strictly.
        $totalRevenue = (float) Payment::where(function ($q) {
            $q->where('status', 'approved')
              ->orWhere('status', 'verified')
              ->orWhere('verification_status', 'verified')
              ->orWhere('verification_status', 'reconciled');
        })->sum('amount');

        $totalExpenses = (float) Expense::where('approval_status', 'approved')->sum('amount');

        $totalAssessed = (float) StudentAccount::sum('total_charges');
        $totalCollectedAr = (float) StudentAccount::sum('total_paid');
        $accountsReceivable = $totalAssessed - $totalCollectedAr;

        $payableTotal = (float) AccountsPayable::sum('amount');
        $payablePaid = (float) AccountsPayable::sum('amount_paid');
        $accountsPayable = $payableTotal - $payablePaid;

        $availableFunds = (float) Fund::where('status', 'active')->sum('current_balance');
        $outstandingBalances = (float) StudentAccount::where('outstanding_balance', '>', 0)->sum('outstanding_balance');
        $pendingRequests = ProcurementRequest::whereIn('status', ['submitted', 'under_review', 'pending'])->count();
        $assetValue = 0.0;
        foreach (Asset::where('asset_status', 'active')->get() as $a) {
            $assetValue += (float) $a->book_value;
        }
        $assetAcquisition = (float) Asset::where('asset_status', 'active')->sum('acquisition_cost');

        // Monthly revenue / expense trend: always last 6 calendar months, zero-filled.
        // DB-agnostic: bucket in PHP so it works on Postgres and MySQL.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = Carbon::now()->startOfMonth()->subMonths($i);
            $months[] = ['key' => $d->format('Y-m'), 'label' => $d->format('M'), 'full' => $d->format('F Y')];
        }
        $monthKeys = array_column($months, 'key');
        $startDate = Carbon::now()->startOfMonth()->subMonths(5)->startOfDay();
        $endDate = Carbon::now()->endOfMonth()->endOfDay();

        $revRows = Payment::whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where(function ($q) {
                $q->where('status', 'approved')->orWhere('status', 'verified')
                  ->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled');
            })->get(['payment_date', 'amount']);
        $revByMonth = array_fill_keys($monthKeys, 0.0);
        foreach ($revRows as $row) {
            $k = Carbon::parse($row->payment_date)->format('Y-m');
            if (array_key_exists($k, $revByMonth)) $revByMonth[$k] += (float) $row->amount;
        }

        $expRows = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('approval_status', 'approved')
            ->get(['expense_date', 'amount']);
        $expByMonth = array_fill_keys($monthKeys, 0.0);
        foreach ($expRows as $row) {
            $k = Carbon::parse($row->expense_date)->format('Y-m');
            if (array_key_exists($k, $expByMonth)) $expByMonth[$k] += (float) $row->amount;
        }

        $revenueLabels = array_column($months, 'label');
        $revenueFullLabels = array_column($months, 'full');
        $revenueData = array_values(array_map(fn($k) => round($revByMonth[$k], 2), $monthKeys));
        $expenseLabels = $revenueLabels;
        $expenseFullLabels = $revenueFullLabels;
        $expenseData = array_values(array_map(fn($k) => round($expByMonth[$k], 2), $monthKeys));

        // Back-compat shape for any cached view expecting $revTrend / $expTrend ({m, t}).
        $revTrend = collect($months)->map(fn($m, $idx) => (object) ['m' => $m['full'], 't' => $revenueData[$idx]])->values();
        $expTrend = collect($months)->map(fn($m, $idx) => (object) ['m' => $m['full'], 't' => $expenseData[$idx]])->values();

        // Budget donut: used vs remaining from real BudgetPlan totals.
        $budgetAllocated = (float) BudgetPlan::sum('allocated_amount');
        $budgetUsed = (float) BudgetPlan::sum('utilized_amount');
        $budgetUsed = min($budgetUsed, $budgetAllocated);
        $budgetRemaining = max(0, $budgetAllocated - $budgetUsed);
        $budgetPct = $budgetAllocated > 0 ? round(($budgetUsed / $budgetAllocated) * 100, 1) : 0.0;
        $budgetYearLabel = BudgetPlan::whereNotNull('fiscal_year')->where('fiscal_year', '<>', '')->orderByDesc('id')->value('fiscal_year')
            ?: (Carbon::now()->format('Y') . '-' . Carbon::now()->addYear()->format('Y'));

        $recentPayments = Payment::with('student')->latest()->take(8)->get();
        $recentExpenses = Expense::latest()->take(8)->get();

        $budgetUtil = BudgetPlan::select('budget_name', 'allocated_amount', 'utilized_amount')->take(8)->get();

        $fundAlloc = Fund::select('fund_name', 'current_balance')->where('status', 'active')->get();
        $fundTotal = (float) $fundAlloc->sum('current_balance');

        return view('admin.fms.dashboard', compact(
            'totalRevenue', 'totalExpenses', 'accountsReceivable', 'accountsPayable',
            'availableFunds', 'outstandingBalances', 'pendingRequests',
            'assetValue', 'assetAcquisition', 'revTrend', 'expTrend',
            'recentPayments', 'recentExpenses', 'budgetUtil', 'fundAlloc',
            'revenueLabels', 'revenueFullLabels', 'revenueData',
            'expenseLabels', 'expenseFullLabels', 'expenseData',
            'budgetAllocated', 'budgetUsed', 'budgetRemaining', 'budgetPct', 'budgetYearLabel',
            'fundTotal'
        ));
    }
}
