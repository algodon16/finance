<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountLedger;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    /**
     * Compare recorded system payments against actual ledger (collection) records.
     * Difference = Actual Amount - System Amount.
     * Difference == 0 => Reconciled, otherwise Variance.
     * Missing ledger entry => Unreconciled.
     */
    public function index(Request $request)
    {
        $recordedStatuses = ['approved', 'posted'];

        $query = Payment::with('student')->whereIn('status', $recordedStatuses);

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('status_filter')) {
            // handled after computation; keep query unfiltered here
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('student_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $allPayments = $query->latest('payment_date')->get();

        $ledgerCredits = AccountLedger::whereIn('payment_id', $allPayments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');

        $ledgerMap = $ledgerCredits->toArray();

        $allRows = $allPayments->map(function ($payment) use ($ledgerMap) {
            $systemAmount = (float) $payment->amount;
            $hasLedger = array_key_exists($payment->id, $ledgerMap);
            $actualAmount = $hasLedger ? (float) $ledgerMap[$payment->id] : 0.0;
            $difference = round($actualAmount - $systemAmount, 2);

            if (! $hasLedger) {
                $status = 'Unreconciled';
            } elseif (abs($difference) < 0.01) {
                $status = 'Reconciled';
            } else {
                $status = 'Variance';
            }

            return (object) [
                'payment' => $payment,
                'system_amount' => $systemAmount,
                'actual_amount' => $actualAmount,
                'difference' => $difference,
                'has_ledger' => $hasLedger,
                'status' => $status,
            ];
        });

        // Summary across the full filtered scope.
        $totalRecords = $allRows->count();
        $reconciled = $allRows->where('status', 'Reconciled')->count();
        $unreconciled = $totalRecords - $reconciled;
        $totalVariance = $allRows->where('status', '!=', 'Reconciled')->sum(fn ($r) => abs($r->difference));

        if ($request->filled('status_filter') && in_array($request->status_filter, ['Reconciled', 'Variance', 'Unreconciled'])) {
            $allRows = $allRows->where('status', $request->status_filter)->values();
        }

        // Manual pagination over computed rows so status filter + pages stay consistent.
        $perPage = 20;
        $page = max(1, (int) $request->get('page', 1));
        $payments = new \Illuminate\Pagination\LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('accountant.reconciliation.index', compact(
            'payments',
            'totalRecords',
            'reconciled',
            'unreconciled',
            'totalVariance'
        ));
    }

    /**
     * View details of a single reconciled/variance transaction.
     * Historical payment records are never modified here.
     */
    public function show($id)
    {
        $payment = Payment::with(['student.studentAccount', 'accountLedgerEntries', 'reviewer'])
            ->findOrFail($id);

        $systemAmount = (float) $payment->amount;
        $actualAmount = (float) $payment->accountLedgerEntries->sum('credit');
        $hasLedger = $payment->accountLedgerEntries->isNotEmpty();
        $difference = round($actualAmount - $systemAmount, 2);

        if (! $hasLedger) {
            $status = 'Unreconciled';
        } elseif (abs($difference) < 0.01) {
            $status = 'Reconciled';
        } else {
            $status = 'Variance';
        }

        return view('accountant.reconciliation.show', compact(
            'payment',
            'systemAmount',
            'actualAmount',
            'difference',
            'hasLedger',
            'status'
        ));
    }
}
