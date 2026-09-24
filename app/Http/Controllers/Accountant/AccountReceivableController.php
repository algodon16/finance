<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FinancialCharge;
use App\Models\Semester;
use App\Models\StudentAccount;
use Illuminate\Http\Request;

class AccountReceivableController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentAccount::with(['student']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('program')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('program', $request->program);
            });
        }

        if ($request->filled('year_level')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('year_level', $request->year_level);
            });
        }

        if ($request->filled('academic_year_id')) {
            $query->whereHas('student.financialCharges', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        if ($request->filled('semester_id')) {
            $query->whereHas('student.financialCharges', function ($q) use ($request) {
                $q->where('semester_id', $request->semester_id);
            });
        }

        if ($request->filled('balance_status')) {
            if ($request->balance_status === 'with_balance') {
                $query->where('outstanding_balance', '>', 0);
            } elseif ($request->balance_status === 'fully_paid') {
                $query->where('outstanding_balance', '<=', 0);
            } elseif ($request->balance_status === 'overdue') {
                $overdueStudentIds = FinancialCharge::where('due_date', '<', today())
                    ->whereNotIn('status', ['paid', 'waived'])
                    ->pluck('student_id')
                    ->unique();
                $query->where('outstanding_balance', '>', 0)
                    ->whereIn('student_id', $overdueStudentIds);
            }
        }

        $accounts = $query->orderBy('outstanding_balance', 'desc')->paginate(20)->withQueryString();

        // Attach earliest unpaid due date per student (for the Due Date column).
        $studentIds = $accounts->getCollection()->pluck('student_id');
        $dueDates = FinancialCharge::whereIn('student_id', $studentIds)
            ->whereNotIn('status', ['paid', 'waived'])
            ->groupBy('student_id')
            ->selectRaw('student_id, MIN(due_date) as due_date')
            ->pluck('due_date', 'student_id');

        $accounts->getCollection()->transform(function ($account) use ($dueDates) {
            $account->due_date = $dueDates[$account->student_id] ?? null;

            return $account;
        });

        // Summary cards (dynamic, across full filtered scope for balance-agnostic totals
        // but global for the header: use unfiltered totals for consistency).
        $totalReceivable = (float) StudentAccount::sum('outstanding_balance');
        $studentsWithBalance = StudentAccount::where('outstanding_balance', '>', 0)->count();

        $overdueStudentIds = FinancialCharge::where('due_date', '<', today())
            ->whereNotIn('status', ['paid', 'waived'])
            ->pluck('student_id')
            ->unique();
        $overdueAccounts = StudentAccount::where('outstanding_balance', '>', 0)
            ->whereIn('student_id', $overdueStudentIds)
            ->count();

        $programs = \App\Models\Student::whereNotNull('program')
            ->where('program', '!=', '')
            ->distinct()
            ->orderBy('program')
            ->pluck('program');

        $yearLevels = \App\Models\Student::whereNotNull('year_level')
            ->distinct()
            ->orderBy('year_level')
            ->pluck('year_level');

        $academicYears = AcademicYear::orderByDesc('is_active')->orderByDesc('id')->get();
        $semesters = Semester::orderByDesc('is_active')->orderByDesc('id')->get();

        return view('accountant.accounts-receivable.index', compact(
            'accounts',
            'totalReceivable',
            'studentsWithBalance',
            'overdueAccounts',
            'programs',
            'yearLevels',
            'academicYears',
            'semesters'
        ));
    }

    public function show($id)
    {
        $account = StudentAccount::with('student')->findOrFail($id);
        $student = $account->student;

        $charges = $student->financialCharges()
            ->with(['financialCategory', 'academicYear', 'semester'])
            ->latest()
            ->get();

        $payments = $student->payments()
            ->whereIn('status', ['approved', 'posted'])
            ->latest('payment_date')
            ->get();

        return view('accountant.accounts-receivable.show', compact('account', 'student', 'charges', 'payments'));
    }
}
