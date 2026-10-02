<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\Fund;
use App\Models\PayablePayment;
use App\Models\Payment;
use App\Models\StudentAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Financial Analytics & Monitoring Dashboard.
     * All figures come from live database records — no hardcoded statistics.
     * The period filter scopes revenue/expenses; budget/fund/AR/AP are
     * point-in-time positions (noted in the view subtitles).
     */
    public function index(Request $request)
    {
        // No period selector on the dashboard: figures always cover This Month.
        [$start, $end, $period, $cStart, $cEnd] = $this->resolvePeriod(new Request(['period' => 'month']));
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $days = max(1, $start->diffInDays($end) + 1);
        $prevStart = $start->copy()->subDays($days)->startOfDay();

        // ---- Revenue (recorded collections only) ----
        $revQ = fn($s, $e) => Payment::where(function ($q) {
            $q->where('status', 'approved')->orWhere('status', 'verified')
              ->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled');
        })->whereBetween('payment_date', [$s->toDateString(), $e->toDateString()]);
        $revenue = (float) (clone $revQ($start, $end))->sum('amount');
        $prevRevenue = (float) (clone $revQ($prevStart, $prevEnd))->sum('amount');

        // ---- Expenses (approved, financially active — each peso counted once) ----
        $expQ = fn($s, $e) => Expense::financiallyActive()->where('approval_status', 'approved')
            ->whereBetween('expense_date', [$s->toDateString(), $e->toDateString()]);
        $expenses = (float) (clone $expQ($start, $end))->sum('amount');
        $prevExpenses = (float) (clone $expQ($prevStart, $prevEnd))->sum('amount');

        $net = $revenue - $expenses;
        $revChange = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1) : ($revenue > 0 ? 100.0 : 0.0);
        $expChange = $prevExpenses > 0 ? round((($expenses - $prevExpenses) / $prevExpenses) * 100, 1) : ($expenses > 0 ? 100.0 : 0.0);

        // ---- Budget position (approved budgets only — never unapproved requests) ----
        $approvedBudget = (float) BudgetPlan::whereIn('status', ['approved', 'active'])->sum('allocated_amount');
        $budgetUtilized = (float) BudgetPlan::whereIn('status', ['approved', 'active'])->sum('utilized_amount');
        $budgetUtilized = min($budgetUtilized, $approvedBudget);
        $availableBudget = max(0, $approvedBudget - $budgetUtilized);
        $budgetRate = $approvedBudget > 0 ? round(($budgetUtilized / $approvedBudget) * 100, 2) : 0.0;

        // ---- Funds (point-in-time) ----
        $funds = Fund::orderBy('fund_name')->get();
        $fundsAvailable = (float) $funds->where('status', 'active')->sum(fn($f) => (float) $f->available_amount);
        $fundRows = $funds->map(fn($f) => [
            'name' => $f->fund_name,
            'total' => (float) $f->initial_balance,
            'allocated' => (float) $f->used_amount,
            'reserved' => (float) $f->reserved_amount,
            'available' => (float) $f->available_amount,
            'rate' => (float) $f->initial_balance > 0 ? round(((float) $f->used_amount / (float) $f->initial_balance) * 100, 1) : 0.0,
        ]);

        // ---- Receivables (point-in-time assessment ledger) ----
        $arAssessed = (float) StudentAccount::sum('total_charges');
        $arCollected = (float) StudentAccount::sum('total_paid');
        $arOutstanding = (float) StudentAccount::where('outstanding_balance', '>', 0)->sum('outstanding_balance');
        $arByProgram = StudentAccount::join('students', 'students.id', '=', 'student_accounts.student_id')
            ->groupBy('students.program')
            ->selectRaw('students.program as program, SUM(total_charges) as assessment, SUM(total_paid) as paid, SUM(outstanding_balance) as outstanding')
            ->orderByDesc('outstanding')->take(5)->get();

        // ---- Payables (point-in-time; overdue + aging from due dates) ----
        $apBase = AccountsPayable::whereNotIn('approval_status', ['rejected', 'cancelled']);
        $apTotal = (float) (clone $apBase)->sum('amount');
        $apPaid = (float) (clone $apBase)->sum('amount_paid');
        $apOutstanding = max(0, $apTotal - $apPaid);
        $apOverdue = (float) (clone $apBase)->where('due_date', '<', today()->toDateString())
            ->selectRaw('SUM(amount - amount_paid) as v')->value('v');
        $apOverdue = max(0, (float) $apOverdue);
        $apAging = [
            'Current' => 0.0, '1–30 Days' => 0.0, '31–60 Days' => 0.0, '61–90 Days' => 0.0, '90+ Days' => 0.0,
        ];
        foreach ((clone $apBase)->get(['due_date', 'amount', 'amount_paid']) as $ap) {
            $rem = (float) $ap->amount - (float) $ap->amount_paid;
            if ($rem <= 0.009) continue;
            $daysOver = today()->diffInDays(Carbon::parse($ap->due_date), false);
            if ($daysOver <= 0) $apAging['Current'] += $rem;
            elseif ($daysOver <= 30) $apAging['1–30 Days'] += $rem;
            elseif ($daysOver <= 60) $apAging['31–60 Days'] += $rem;
            elseif ($daysOver <= 90) $apAging['61–90 Days'] += $rem;
            else $apAging['90+ Days'] += $rem;
        }

        // ---- Monthly series for the selected window (cap 12 months) ----
        $months = $this->monthBuckets($start, $end);
        $monthKeys = array_column($months, 'key');
        $revByMonth = array_fill_keys($monthKeys, 0.0);
        foreach (Payment::where(function ($q) {
            $q->where('status', 'approved')->orWhere('status', 'verified')
              ->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled');
        })->whereBetween('payment_date', [Carbon::parse($months[0]['key'].'-01')->toDateString(), $end->toDateString()])->get(['payment_date', 'amount']) as $row) {
            $k = Carbon::parse($row->payment_date)->format('Y-m');
            if (array_key_exists($k, $revByMonth)) $revByMonth[$k] += (float) $row->amount;
        }
        $expByMonth = array_fill_keys($monthKeys, 0.0);
        foreach (Expense::financiallyActive()->where('approval_status', 'approved')
            ->whereBetween('expense_date', [Carbon::parse($months[0]['key'].'-01')->toDateString(), $end->toDateString()])->get(['expense_date', 'amount']) as $row) {
            $k = Carbon::parse($row->expense_date)->format('Y-m');
            if (array_key_exists($k, $expByMonth)) $expByMonth[$k] += (float) $row->amount;
        }
        $revData = array_values(array_map(fn($k) => round($revByMonth[$k], 2), $monthKeys));
        $expData = array_values(array_map(fn($k) => round($expByMonth[$k], 2), $monthKeys));
        $netData = array_map(fn($i) => round($revData[$i] - $expData[$i], 2), array_keys($revData));
        $monthLabels = array_column($months, 'label');
        $monthFull = array_column($months, 'full');

        // ---- Expense breakdown by existing category values (in-period) ----
        $expBreak = Expense::financiallyActive()->where('approval_status', 'approved')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('expense_category')->selectRaw('expense_category as cat, SUM(amount) as total')
            ->orderByDesc('total')->get();
        $expBreakTotal = (float) $expBreak->sum('total');
        $expBreakTop = $expBreak->take(7)->map(fn($r) => [
            'cat' => $r->cat ?: 'Uncategorized', 'total' => (float) $r->total,
            'pct' => $expBreakTotal > 0 ? round(((float) $r->total / $expBreakTotal) * 100, 1) : 0.0,
        ]);
        $expBreakOther = $expBreakTotal - (float) $expBreakTop->sum('total');

        // ---- Department budget analytics (approved budgets, real departments) ----
        $deptRows = BudgetPlan::whereIn('status', ['approved', 'active'])
            ->selectRaw('department, SUM(allocated_amount) as allocated, SUM(utilized_amount) as utilized')
            ->groupBy('department')->orderByDesc('allocated')->take(8)->get()->map(function ($r) {
                $a = (float) $r->allocated;
                $u = min((float) $r->utilized, $a);
                return ['department' => $r->department ?: '—', 'allocated' => $a, 'utilized' => $u,
                    'remaining' => $a - $u, 'rate' => $a > 0 ? round(($u / $a) * 100, 2) : 0.0];
            });

        // ---- Recent financial transactions (actual postings, not workflow logs) ----
        $recentPay = Payment::where(function ($q) {
            $q->where('status', 'approved')->orWhere('status', 'verified')
              ->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled');
        })->latest('payment_date')->take(5)->get()
            ->map(fn($p) => (object) ['date' => $p->payment_date, 'ref' => $p->transaction_number ?? ('PAY-'.$p->id),
                'type' => 'Revenue', 'desc' => $p->fee_category ?? 'Collection', 'amount' => (float) $p->amount, 'status' => $p->status]);
        $recentExp = Expense::financiallyActive()->where('approval_status', 'approved')->latest('expense_date')->take(5)->get()
            ->map(fn($e) => (object) ['date' => $e->expense_date, 'ref' => $e->reference_number,
                'type' => 'Expense', 'desc' => $e->expense_category, 'amount' => (float) $e->amount, 'status' => 'approved']);
        $recentTx = $recentPay->concat($recentExp)->sortByDesc('date')->take(10)->values();

        $periodLabel = $this->periodLabel($period, $start, $end, $cStart, $cEnd);

        // ---- Request → fulfillment pipeline (live records, same design, no extra graphs) ----
        $frForReview = (int) FinancialRequest::where('status', 'submitted')->count();
        $frForProcessing = (int) FinancialRequest::where('status', 'approved')->count();
        $frCompleted = (int) FinancialRequest::where('status', 'completed')->count();
        $actualExpenses = (float) Expense::financiallyActive()->where('approval_status', 'approved')->where('payment_status', 'paid')->sum('amount');
        $totalDisbursements = (float) PayablePayment::sum('amount');

        return view('accountant.dashboard', compact(
            'period', 'cStart', 'cEnd', 'periodLabel',
            'revenue', 'prevRevenue', 'revChange', 'expenses', 'prevExpenses', 'expChange', 'net',
            'approvedBudget', 'budgetUtilized', 'availableBudget', 'budgetRate',
            'fundsAvailable', 'fundRows',
            'arAssessed', 'arCollected', 'arOutstanding', 'arByProgram',
            'apTotal', 'apPaid', 'apOutstanding', 'apOverdue', 'apAging',
            'monthLabels', 'monthFull', 'revData', 'expData', 'netData',
            'expBreakTop', 'expBreakOther', 'expBreakTotal',
            'deptRows', 'recentTx',
            'frForReview', 'frForProcessing', 'frCompleted', 'actualExpenses', 'totalDisbursements'
        ));
    }

    /** Resolve today/week/month/quarter/year/custom into start/end + custom echoes. */
    protected function resolvePeriod(Request $request): array
    {
        $period = $request->input('period', 'month');
        $now = Carbon::now();
        $cStart = $request->input('start_date');
        $cEnd = $request->input('end_date');
        switch ($period) {
            case 'today':
                $start = $now->copy()->startOfDay(); $end = $now->copy()->endOfDay(); break;
            case 'week':
                $start = $now->copy()->startOfWeek(); $end = $now->copy()->endOfWeek(); break;
            case 'quarter':
                $start = $now->copy()->firstOfQuarter()->startOfDay(); $end = $now->copy()->lastOfQuarter()->endOfDay(); break;
            case 'year':
                $start = $now->copy()->startOfYear(); $end = $now->copy()->endOfYear(); break;
            case 'academic_year':
                $start = $now->copy()->month >= 6 ? $now->copy()->month(6)->startOfMonth() : $now->copy()->subYear()->month(6)->startOfMonth();
                $end = $start->copy()->addYear()->subDay()->endOfDay(); break;
            case 'custom':
                try {
                    $start = $cStart ? Carbon::parse($cStart)->startOfDay() : $now->copy()->startOfMonth();
                    $end = $cEnd ? Carbon::parse($cEnd)->endOfDay() : $now->copy()->endOfDay();
                } catch (\Throwable $e) {
                    $start = $now->copy()->startOfMonth(); $end = $now->copy()->endOfDay();
                }
                if ($end->lt($start)) [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                break;
            case 'month':
            default:
                $period = 'month';
                $start = $now->copy()->startOfMonth(); $end = $now->copy()->endOfMonth(); break;
        }
        return [$start, $end, $period, $cStart, $cEnd];
    }

    /** Month buckets covering start→end (cap 12, most recent). */
    protected function monthBuckets(Carbon $start, Carbon $end): array
    {
        $cursor = $start->copy()->startOfMonth();
        $buckets = [];
        while ($cursor->lte($end) && count($buckets) < 24) {
            $buckets[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M'), 'full' => $cursor->format('F Y')];
            $cursor->addMonth();
        }
        if (count($buckets) > 12) $buckets = array_slice($buckets, -12);
        if (empty($buckets)) {
            $buckets[] = ['key' => $end->format('Y-m'), 'label' => $end->format('M'), 'full' => $end->format('F Y')];
        }
        return array_values($buckets);
    }

    protected function periodLabel(string $period, Carbon $start, Carbon $end, $cStart, $cEnd): string
    {
        return match ($period) {
            'today' => 'Today · '.$start->format('M d, Y'),
            'week' => 'This Week · '.$start->format('M d').' – '.$end->format('M d, Y'),
            'month' => 'This Month · '.$start->format('F Y'),
            'quarter' => 'This Quarter · '.$start->format('M Y').' – '.$end->format('M Y'),
            'year' => 'This Year · '.$start->format('Y'),
            'academic_year' => 'Academic Year · '.$start->format('M Y').' – '.$end->format('M Y'),
            default => 'Custom · '.$start->format('M d, Y').' – '.$end->format('M d, Y'),
        };
    }
}
