<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\Asset;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Payment;
use App\Models\StudentAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.fms.reports.index');
    }

    protected function range(Request $r): array
    {
        return [$r->get('date_from', now()->startOfYear()->toDateString()), $r->get('date_to', today()->toDateString())];
    }

    protected function logView(string $name, Request $r): void
    {
        AuditService::log('report', 'financial_reports', null, null, null, "Admin ".auth()->user()->name." generated {$name} ({$r->get('date_from', '')} to {$r->get('date_to', '')}).");
    }

    public function revenue(Request $request)
    {
        [$from, $to] = $this->range($request);
        $records = Payment::with('student')
            ->whereBetween('payment_date', [$from, $to])
            ->when($request->filled('fee_category'), fn($q) => $q->where('fee_category', $request->fee_category))
            ->when($request->filled('payment_method'), fn($q) => $q->where('payment_method', $request->payment_method))
            ->orderBy('payment_date')->get();
        $total = (float) $records->whereIn('status', ['approved', 'verified'])->sum('amount')
            + (float) $records->whereIn('verification_status', ['verified', 'reconciled'])->whereNotIn('status', ['approved', 'verified'])->sum('amount');
        $this->logView('Revenue Report', $request);
        return view('admin.fms.reports.revenue', compact('records', 'total', 'from', 'to'));
    }

    public function expenses(Request $request)
    {
        [$from, $to] = $this->range($request);
        $records = Expense::financiallyActive()->whereBetween('expense_date', [$from, $to])
            ->when($request->filled('department'), fn($q) => $q->where('department', $request->department))
            ->when($request->filled('expense_category'), fn($q) => $q->where('expense_category', $request->expense_category))
            ->orderBy('expense_date')->get();
        $total = (float) $records->where('approval_status', 'approved')->sum('amount');
        $this->logView('Expense Report', $request);
        return view('admin.fms.reports.expenses', compact('records', 'total', 'from', 'to'));
    }

    public function receivables(Request $request)
    {
        $records = StudentAccount::with('student')
            ->when($request->filled('only_outstanding'), fn($q) => $q->where('outstanding_balance', '>', 0))
            ->orderBy('outstanding_balance', 'desc')->get();
        $total = (float) $records->sum('outstanding_balance');
        $this->logView('Receivables Report', $request);
        return view('admin.fms.reports.receivables', compact('records', 'total'));
    }

    public function payables(Request $request)
    {
        $records = AccountsPayable::orderBy('due_date')->get();
        $aging = ['current' => 0, 'due_soon' => 0, 'overdue' => 0];
        foreach ($records as $p) {
            $rem = (float) $p->remaining_balance;
            if ($rem <= 0) continue;
            if ($p->due_date < today()) $aging['overdue'] += $rem;
            elseif ($p->due_date->diffInDays(today()) <= 7) $aging['due_soon'] += $rem;
            else $aging['current'] += $rem;
        }
        $this->logView('Payables Aging Report', $request);
        return view('admin.fms.reports.payables', compact('records', 'aging'));
    }

    public function budgets(Request $request)
    {
        $plans = BudgetPlan::with('allocations')->get();
        $this->logView('Budget vs Actual Report', $request);
        return view('admin.fms.reports.budgets', compact('plans'));
    }

    public function assets(Request $request)
    {
        $records = Asset::orderBy('asset_name')->get();
        $totals = [
            'acquisition' => (float) $records->sum('acquisition_cost'),
            'accumulated' => round($records->sum(fn($a) => (float) $a->accumulated_depreciation), 2),
            'book' => round($records->sum(fn($a) => (float) $a->book_value), 2),
        ];
        $this->logView('Asset Register Report', $request);
        return view('admin.fms.reports.assets', compact('records', 'totals'));
    }

    public function executive(Request $request)
    {
        $revenue = (float) Payment::where(fn($q) => $q->where('status', 'approved')->orWhere('status', 'verified')->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled'))->sum('amount');
        $expenses = (float) Expense::financiallyActive()->where('approval_status', 'approved')->sum('amount');
        $receivable = (float) StudentAccount::sum('outstanding_balance');
        $payables = (float) AccountsPayable::all()->sum(fn($p) => (float) $p->remaining_balance);
        $funds = (float) Fund::where('status', 'active')->sum('current_balance');
        $net = $revenue - $expenses;
        $monthly = Payment::select(DB::raw("to_char(payment_date,'YYYY-MM') as m"), DB::raw('SUM(amount) as t'))
            ->whereNotNull('payment_date')->groupBy('m')->orderBy('m', 'desc')->take(12)->get()->reverse()->values();
        $this->logView('Executive Financial Overview', $request);
        return view('admin.fms.reports.executive', compact('revenue', 'expenses', 'receivable', 'payables', 'funds', 'net', 'monthly'));
    }

    /** Reconciliation inbox — same ReconciliationRecord rows the Accountant submitted. */
    public function reconciliations(Request $request)
    {
        $q = \App\Models\ReconciliationRecord::with('payment')->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->status);
        $records = $q->paginate(15)->withQueryString();
        $counts = [
            'pending' => (int) \App\Models\ReconciliationRecord::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (int) \App\Models\ReconciliationRecord::whereIn('status', ['approved', 'reviewed', 'reconciled'])->count(),
            'rejected' => (int) \App\Models\ReconciliationRecord::whereIn('status', ['rejected', 'for_revision', 'revision'])->count(),
        ];
        return view('admin.fms.reports.reconciliations', compact('records', 'counts'));
    }

    public function approveReconciliation(Request $request, \App\Models\ReconciliationRecord $record)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($record->status, ['submitted', 'under_review', 'draft'], true), 422, 'Only submitted reconciliations can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        $record->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'admin_remarks' => $request->input('admin_remarks') ?: $record->admin_remarks]);
        AuditService::log('approve', 'reconciliation_records', (string) $record->id, ['status' => 'submitted'], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Reconciliation {$record->reference_number} — part of official financial records.");
        \App\Services\WorkflowService::notifyUser($record->prepared_by, "Reconciliation {$record->reference_number} has been approved.", "It is now part of the official financial records.");
        return back()->with('success', 'Reconciliation approved.');
    }

    public function rejectReconciliation(Request $request, \App\Models\ReconciliationRecord $record)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($record->status, ['submitted', 'under_review', 'draft'], true), 422, 'Only submitted reconciliations can be rejected.');
        $record->update(['status' => 'rejected', 'admin_remarks' => trim(($data['admin_remarks'] ?? '').($data['admin_remarks'] ? ' | ' : '').$data['rejection_reason']), 'reviewed_at' => now(), 'reviewed_by' => auth()->id(), 'revision_number' => ((int) $record->revision_number) + 1]);
        AuditService::log('reject', 'reconciliation_records', (string) $record->id, ['status' => 'submitted'], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Reconciliation {$record->reference_number}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($record->prepared_by, "Reconciliation {$record->reference_number} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit.");
        return back()->with('success', 'Reconciliation rejected and returned for revision.');
    }

    /**
     * Password gate for Financial Reporting and Compliance.
     * Verifies against the currently authenticated account; the plain-text
     * password is never stored, logged, or returned.
     */
    public function verifyAccess(Request $request)
    {
        $data = $request->validate(['password' => 'required|string|max:255']);

        $key = 'reports-verify:'.auth()->id().'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        if (! Hash::check($data['password'], auth()->user()->password)) {
            RateLimiter::hit($key, 300);
            try {
                AuditService::log('reports_access_denied', 'financial_reports', (string) auth()->id());
            } catch (\Throwable $e) {
            }
            return response()->json(['message' => 'Incorrect password. Please try again.'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->put('reports.unlocked', true);
        $request->session()->put('reports.unlocked_at', time());

        try {
            AuditService::log('reports_access', 'financial_reports', (string) auth()->id());
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true]);
    }
}
