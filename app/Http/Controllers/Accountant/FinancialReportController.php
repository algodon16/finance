<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AccountLedger;
use App\Models\FinancialCharge;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public const REPORT_TYPES = [
        'daily_collection' => 'Daily Collection Report',
        'monthly_collection' => 'Monthly Collection Report',
        'payment_summary' => 'Payment Summary Report',
        'accounts_receivable' => 'Accounts Receivable Report',
        'outstanding_balance' => 'Outstanding Balance Report',
        'reconciliation' => 'Reconciliation Report',
    ];

    public function index(Request $request)
    {
        $programs = Student::whereNotNull('program')->where('program', '!=', '')
            ->distinct()->orderBy('program')->pluck('program');
        $yearLevels = Student::whereNotNull('year_level')
            ->distinct()->orderBy('year_level')->pluck('year_level');
        $academicYears = AcademicYear::orderByDesc('is_active')->orderByDesc('id')->get();
        $semesters = Semester::orderByDesc('is_active')->orderByDesc('id')->get();
        $paymentMethods = ['cash', 'bank_transfer', 'gcash', 'maya', 'other'];

        $report = null;

        if ($request->filled('report_type') && array_key_exists($request->report_type, self::REPORT_TYPES)) {
            $report = $this->generate($request);
        }

        return view('accountant.financial-reports.index', array_merge(compact(
            'programs',
            'yearLevels',
            'academicYears',
            'semesters',
            'paymentMethods'
        ), ['report' => $report]));
    }

    protected function generate(Request $request): array
    {
        $type = $request->report_type;
        $dateFrom = $request->date_from ?: null;
        $dateTo = $request->date_to ?: null;

        $title = self::REPORT_TYPES[$type];
        $rows = [];
        $columns = [];
        $summary = [];
        $error = null;

        // Guard the most common "zero records" user error: an inverted range
        // can never match (date >= from AND date <= to), so explain instead
        // of silently returning 0 transactions / ₱0.00.
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $error = 'Date From cannot be after Date To. Adjust the range and generate again.';
        } elseif (in_array($type, ['daily_collection', 'monthly_collection', 'payment_summary'])) {
            $data = $this->paymentReportData($request, $type);
            $rows = $data['rows'];
            $columns = $data['columns'];
            $summary = $data['summary'];
        } elseif (in_array($type, ['accounts_receivable', 'outstanding_balance'])) {
            $data = $this->receivableReportData($request, $type);
            $rows = $data['rows'];
            $columns = $data['columns'];
            $summary = $data['summary'];
        } elseif ($type === 'reconciliation') {
            $data = $this->reconciliationReportData($request);
            $rows = $data['rows'];
            $columns = $data['columns'];
            $summary = $data['summary'];
        }

        return [
            'type' => $type,
            'title' => $title,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
            'error' => $error,
            'applied_filters' => $this->appliedFilters($request),
            'generated_date' => Carbon::now()->format('M d, Y h:i A'),
            'generated_by' => auth()->user()->name ?? 'Accountant',
        ];
    }

    /**
     * Human-readable list of the filters actually applied, so an empty
     * result explains itself instead of looking broken.
     */
    protected function appliedFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->date_from
                ? Carbon::parse($request->date_from)->format('M d, Y')
                : 'Beginning';
            $to = $request->date_to
                ? Carbon::parse($request->date_to)->format('M d, Y')
                : 'Present';
            $filters[] = "Date: {$from} to {$to}";
        }

        if ($request->filled('program')) {
            $filters[] = 'Program: ' . $request->program;
        }

        if ($request->filled('year_level')) {
            $filters[] = 'Year Level: ' . $request->year_level;
        }

        if ($request->filled('semester_id') && ($s = Semester::find($request->semester_id))) {
            $filters[] = 'Semester: ' . $s->name;
        }

        if ($request->filled('academic_year_id') && ($y = AcademicYear::find($request->academic_year_id))) {
            $filters[] = 'Academic Year: ' . $y->name;
        }

        if ($request->filled('payment_method')) {
            $filters[] = 'Method: ' . ucfirst(str_replace('_', ' ', $request->payment_method));
        }

        return $filters;
    }

    /**
     * System's collection definition: only approved/posted payments count
     * as collected. This mirrors PaymentApprovalService (which posts the
     * ledger entry on approval), the cashier verified-collections count,
     * and the accountant dashboard totals. Rejected/pending/cancelled
     * payments are never collections. No new statuses are introduced.
     */
    protected function basePaymentQuery(Request $request)
    {
        $query = Payment::with('student')->whereIn('status', ['approved', 'posted']);

        // whereDate comparisons are inclusive: date_from 00:00:00 through
        // date_to 23:59:59, so boundary-day records are never excluded.
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('program')) {
            $query->whereHas('student', fn ($q) => $q->where('program', $request->program));
        }

        if ($request->filled('year_level')) {
            $query->whereHas('student', fn ($q) => $q->where('year_level', $request->year_level));
        }

        if ($request->filled('academic_year_id') || $request->filled('semester_id')) {
            $query->whereHas('student.financialCharges', function ($q) use ($request) {
                if ($request->filled('academic_year_id')) {
                    $q->where('academic_year_id', $request->academic_year_id);
                }
                if ($request->filled('semester_id')) {
                    $q->where('semester_id', $request->semester_id);
                }
            });
        }

        return $query;
    }

    protected function paymentReportData(Request $request, string $type): array
    {
        if ($type === 'monthly_collection') {
            // Group in PHP (DB-agnostic) by year-month of payment_date.
            // The base query already constrains payment_date to the selected
            // range, so only months inside that range can appear.
            $grouped = $this->basePaymentQuery($request)
                ->get(['payment_date', 'amount'])
                ->groupBy(fn ($p) => $p->payment_date ? Carbon::parse($p->payment_date)->format('Y-m') : 'Undated')
                ->sortKeys()
                ->map(fn ($items, $period) => [
                    $period === 'Undated' ? 'Undated' : Carbon::createFromFormat('Y-m', $period)->format('F Y'),
                    $items->count(),
                    '₱' . number_format($items->sum('amount'), 2),
                ])->values()->toArray();

            $rows = $grouped;

            $columns = ['Month', 'Transactions', 'Total Collections'];
        } elseif ($type === 'daily_collection') {
            // Group by calendar day of the actual payment transaction date.
            $grouped = $this->basePaymentQuery($request)
                ->get(['payment_date', 'amount'])
                ->groupBy(fn ($p) => $p->payment_date ? Carbon::parse($p->payment_date)->format('Y-m-d') : 'Undated')
                ->sortKeys()
                ->map(fn ($items, $day) => [
                    $day === 'Undated' ? 'Undated' : Carbon::createFromFormat('Y-m-d', $day)->format('M d, Y'),
                    $items->count(),
                    '₱' . number_format($items->sum('amount'), 2),
                ])->values()->toArray();

            $rows = $grouped;

            $columns = ['Date', 'Transactions', 'Total Collections'];
        } else {
            $payments = $this->basePaymentQuery($request)->latest('payment_date')->get();

            $rows = $payments->map(fn ($p) => [
                $p->payment_date ? $p->payment_date->format('M d, Y') : 'N/A',
                $p->student->student_number ?? 'N/A',
                $p->student->full_name ?? 'N/A',
                $p->description ?? 'N/A',
                '₱' . number_format($p->amount, 2),
                ucfirst(str_replace('_', ' ', $p->payment_method ?? '')),
                $p->reference_number ?? 'N/A',
                ucfirst(str_replace('_', ' ', $p->status)),
            ])->toArray();

            $columns = ['Date', 'Student ID', 'Student Name', 'Payment For', 'Amount', 'Method', 'Reference', 'Status'];
        }

        $totals = $this->basePaymentQuery($request)
            ->selectRaw('COUNT(*) as count, SUM(amount) as total')
            ->first();

        $summary = [
            'Total Transactions' => number_format($totals->count ?? 0),
            'Total Collections' => '₱' . number_format($totals->total ?? 0, 2),
        ];

        return compact('rows', 'columns', 'summary');
    }

    protected function receivableReportData(Request $request, string $type): array
    {
        $query = StudentAccount::with('student');

        if ($type === 'outstanding_balance') {
            $query->where('outstanding_balance', '>', 0);
        }

        if ($request->filled('program')) {
            $query->whereHas('student', fn ($q) => $q->where('program', $request->program));
        }

        if ($request->filled('year_level')) {
            $query->whereHas('student', fn ($q) => $q->where('year_level', $request->year_level));
        }

        if ($request->filled('academic_year_id') || $request->filled('semester_id')) {
            $query->whereHas('student.financialCharges', function ($q) use ($request) {
                if ($request->filled('academic_year_id')) {
                    $q->where('academic_year_id', $request->academic_year_id);
                }
                if ($request->filled('semester_id')) {
                    $q->where('semester_id', $request->semester_id);
                }
            });
        }

        $accounts = $query->orderByDesc('outstanding_balance')->get();

        $studentIds = $accounts->pluck('student_id');
        $dueDates = FinancialCharge::whereIn('student_id', $studentIds)
            ->whereNotIn('status', ['paid', 'waived'])
            ->groupBy('student_id')
            ->selectRaw('student_id, MIN(due_date) as due_date')
            ->pluck('due_date', 'student_id');

        $rows = $accounts->map(function ($a) use ($dueDates) {
            return [
                $a->student->student_number ?? 'N/A',
                $a->student->full_name ?? 'N/A',
                $a->student->program ?? 'N/A',
                $a->student->year_level ?? 'N/A',
                '₱' . number_format($a->total_charges ?? 0, 2),
                '₱' . number_format($a->total_paid ?? 0, 2),
                '₱' . number_format($a->outstanding_balance ?? 0, 2),
                isset($dueDates[$a->student_id]) ? Carbon::parse($dueDates[$a->student_id])->format('M d, Y') : 'N/A',
            ];
        })->toArray();

        $columns = ['Student ID', 'Student Name', 'Program', 'Year Level', 'Total Assessment', 'Total Paid', 'Outstanding Balance', 'Due Date'];

        $summary = [
            'Total Receivable' => '₱' . number_format($accounts->sum('outstanding_balance'), 2),
            'Students Listed' => number_format($accounts->count()),
        ];

        return compact('rows', 'columns', 'summary');
    }

    protected function reconciliationReportData(Request $request): array
    {
        $query = Payment::with('student')->whereIn('status', ['approved', 'posted']);

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->latest('payment_date')->get();

        $credits = AccountLedger::whereIn('payment_id', $payments->pluck('id'))
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(credit) as total_credit')
            ->pluck('total_credit', 'payment_id');

        $rows = [];
        $reconciled = 0;
        $variance = 0;
        $totalVariance = 0.0;

        foreach ($payments as $payment) {
            $system = (float) $payment->amount;
            $hasLedger = array_key_exists($payment->id, $credits->toArray());
            $actual = $hasLedger ? (float) $credits[$payment->id] : 0.0;
            $difference = round($actual - $system, 2);

            if (! $hasLedger) {
                $status = 'Unreconciled';
                $variance++;
                $totalVariance += abs($difference);
            } elseif (abs($difference) < 0.01) {
                $status = 'Reconciled';
                $reconciled++;
            } else {
                $status = 'Variance';
                $variance++;
                $totalVariance += abs($difference);
            }

            $rows[] = [
                $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A',
                $payment->reference_number ?? 'N/A',
                $payment->student->full_name ?? 'N/A',
                '₱' . number_format($system, 2),
                '₱' . number_format($actual, 2),
                '₱' . number_format($difference, 2),
                $status,
            ];
        }

        $columns = ['Date', 'Reference', 'Student', 'System Amount', 'Actual Amount', 'Difference', 'Status'];

        $summary = [
            'Total Records' => number_format($payments->count()),
            'Reconciled' => number_format($reconciled),
            'Unreconciled / Variance' => number_format($variance),
            'Total Variance' => '₱' . number_format($totalVariance, 2),
        ];

        return compact('rows', 'columns', 'summary');
    }
}
