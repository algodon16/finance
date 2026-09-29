<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountLedger;
use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\Fund;
use App\Models\FundAllocation;
use App\Models\Payment;
use App\Models\StudentAccount;

class DashboardController extends Controller
{
    public function index()
    {
        // Financial records only: approved + posted payments.
        $recordedStatuses = ['approved', 'posted'];

        $totalCollections = (float) Payment::whereIn('status', $recordedStatuses)->sum('amount');

        $outstandingBalance = (float) StudentAccount::sum('outstanding_balance');

        $totalPayments = Payment::whereIn('status', $recordedStatuses)->count();

        // Unreconciled = recorded payments whose ledger credit does not match the system amount
        // (includes payments with no ledger entry at all).
        $recordedPayments = Payment::whereIn('status', $recordedStatuses)->get(['id', 'amount']);
        $ledgerCredits = AccountLedger::whereIn('payment_id', $recordedPayments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');

        $unreconciledCount = 0;
        foreach ($recordedPayments as $payment) {
            $actual = (float) ($ledgerCredits[$payment->id] ?? 0);
            $hasLedger = array_key_exists($payment->id, $ledgerCredits->toArray());
            if (! $hasLedger || abs($actual - (float) $payment->amount) > 0.009) {
                $unreconciledCount++;
            }
        }

        $recentTransactions = Payment::with('student')
            ->whereIn('status', $recordedStatuses)
            ->latest('payment_date')
            ->take(10)
            ->get();

        // Outstanding balances grouped by program (real assessment/payment data).
        $outstandingByProgram = StudentAccount::join('students', 'students.id', '=', 'student_accounts.student_id')
            ->groupBy('students.program')
            ->selectRaw('students.program as program, SUM(total_charges) as assessment, SUM(total_paid) as paid, SUM(outstanding_balance) as outstanding')
            ->orderByDesc('outstanding')
            ->get();

        // Recent reconciliation snapshot (system vs actual ledger records).
        $reconPayments = Payment::whereIn('status', $recordedStatuses)
            ->latest('payment_date')
            ->take(5)
            ->get(['id', 'amount', 'payment_date', 'created_at']);
        $reconCredits = AccountLedger::whereIn('payment_id', $reconPayments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');
        $reconMap = $reconCredits->toArray();

        $reconciliationSummary = $reconPayments->map(function ($payment) use ($reconMap) {
            $system = (float) $payment->amount;
            $hasLedger = array_key_exists($payment->id, $reconMap);
            $actual = $hasLedger ? (float) $reconMap[$payment->id] : 0.0;
            $difference = round($actual - $system, 2);

            return (object) [
                'date' => $payment->payment_date ?? optional($payment->created_at)->toDateString(),
                'system_amount' => $system,
                'actual_amount' => $actual,
                'difference' => $difference,
                'status' => (! $hasLedger || abs($difference) >= 0.01)
                    ? ($hasLedger ? 'Variance' : 'Unreconciled')
                    : 'Reconciled',
            ];
        });

        // Submission workflow aggregates (real DB values).
        $pendingBudget = BudgetPlan::whereIn('status', ['submitted', 'under_review'])->count();
        $pendingAlloc = FundAllocation::whereIn('status', ['submitted', 'under_review'])->count();
        $pendingExpense = Expense::whereIn('approval_status', ['submitted', 'under_review'])->count();
        $pendingPayable = AccountsPayable::whereIn('approval_status', ['submitted', 'under_review'])->count();
        $pendingRequest = FinancialRequest::whereIn('status', ['submitted', 'under_review'])->count();
        $pendingApproval = $pendingBudget + $pendingAlloc + $pendingExpense + $pendingPayable + $pendingRequest;

        $approvedPlans = BudgetPlan::whereIn('status', ['approved', 'active'])->count()
            + FundAllocation::where('status', 'approved')->count()
            + Expense::financiallyActive()->where('approval_status', 'approved')->count()
            + AccountsPayable::where('approval_status', 'approved')->count()
            + FinancialRequest::whereIn('status', ['approved', 'completed'])->count();

        $rejectedPlans = BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->count()
            + FundAllocation::whereIn('status', ['for_revision', 'revision', 'rejected'])->count()
            + Expense::whereIn('approval_status', ['for_revision', 'revision', 'rejected'])->count()
            + AccountsPayable::whereIn('approval_status', ['for_revision', 'revision', 'rejected'])->count()
            + FinancialRequest::whereIn('status', ['for_revision', 'revision', 'rejected'])->count();

        $totalBudgetProposals = BudgetPlan::count();
        $totalExpenseProposals = Expense::count();

        $pendingBreakdown = [
            ['label' => 'Budget proposals', 'count' => $pendingBudget, 'route' => 'accountant.budgets.index'],
            ['label' => 'Fund allocations', 'count' => $pendingAlloc, 'route' => 'accountant.fund-allocations.index'],
            ['label' => 'Expense proposals', 'count' => $pendingExpense, 'route' => 'accountant.expenses.index'],
            ['label' => 'Accounts payable', 'count' => $pendingPayable, 'route' => 'accountant.payables.index'],
            ['label' => 'Financial requests', 'count' => $pendingRequest, 'route' => 'accountant.financial-requests.index'],
        ];

        $recentSubmissions = BudgetPlan::whereIn('status', ['submitted', 'under_review'])->latest('submitted_at')->take(3)->get()
            ->map(fn($r) => (object) ['type' => 'Budget', 'desc' => $r->budget_name, 'amount' => $r->allocated_amount, 'date' => $r->submitted_at, 'status' => $r->status]);
        $recentApproved = BudgetPlan::whereIn('status', ['approved', 'active'])->latest('approved_at')->take(3)->get()
            ->map(fn($r) => (object) ['type' => 'Budget', 'desc' => $r->budget_name, 'amount' => $r->allocated_amount, 'date' => $r->approved_at, 'status' => $r->status]);
        $recentRejected = BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->latest('reviewed_at')->take(3)->get()
            ->map(fn($r) => (object) ['type' => 'Budget', 'desc' => $r->budget_name, 'amount' => $r->allocated_amount, 'date' => $r->reviewed_at, 'status' => $r->status]);

        $recentAudits = AuditLog::with('user')->latest('id')->take(8)->get();

        // Financial planning aggregates.
        $availableFunds = (float) Fund::where('status', 'active')->get()->sum(fn($f) => (float) $f->available_amount);
        $approvedBudget = (float) BudgetPlan::whereIn('status', ['approved', 'active'])->sum('allocated_amount');
        $allocatedFunds = (float) FundAllocation::where('status', 'approved')->sum('amount');
        $budgetUtilized = (float) BudgetPlan::whereIn('status', ['approved', 'active'])->sum('utilized_amount');
        $remainingBudget = $approvedBudget - $budgetUtilized;

        $budgetUtilization = BudgetPlan::whereIn('status', ['approved', 'active'])
            ->orderByDesc('allocated_amount')->take(6)->get();
        $fundOverview = Fund::where('status', 'active')->orderByDesc('current_balance')->take(6)->get();
        $expenseSummary = Expense::where('approval_status', 'approved')->orderByDesc('approved_at')->take(5)->get();
        $payableSummary = AccountsPayable::orderByDesc('created_at')->take(5)->get();
        $recentApprovedPlans = BudgetPlan::whereIn('status', ['approved', 'active'])->latest('approved_at')->take(4)->get();
        $recentRejectedPlans = BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->latest('reviewed_at')->take(4)->get();

        return view('accountant.dashboard', compact(
            'totalCollections',
            'outstandingBalance',
            'totalPayments',
            'unreconciledCount',
            'recentTransactions',
            'outstandingByProgram',
            'reconciliationSummary',
            'pendingApproval',
            'approvedPlans',
            'rejectedPlans',
            'totalBudgetProposals',
            'totalExpenseProposals',
            'pendingBreakdown',
            'recentSubmissions',
            'recentApproved',
            'recentRejected',
            'recentAudits',
            'availableFunds',
            'approvedBudget',
            'allocatedFunds',
            'remainingBudget',
            'budgetUtilization',
            'fundOverview',
            'expenseSummary',
            'payableSummary',
            'recentApprovedPlans',
            'recentRejectedPlans'
        ));
    }
}
