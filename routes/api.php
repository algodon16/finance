<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BCP Financial Management System — RESTful API (admin-only via Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'api.fresh'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'api.fresh', 'role:admin'])->prefix('fms')->name('api.fms.')->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'total_revenue' => (float) \App\Models\Payment::where(fn($q) => $q->where('status', 'approved')->orWhere('status', 'verified')->orWhere('verification_status', 'verified')->orWhere('verification_status', 'reconciled'))->sum('amount'),
            'total_expenses' => (float) \App\Models\Expense::financiallyActive()->where('approval_status', 'approved')->sum('amount'),
            'accounts_receivable' => (float) \App\Models\StudentAccount::sum('outstanding_balance'),
            'available_funds' => (float) \App\Models\Fund::where('status', 'active')->sum('current_balance'),
        ]);
    });
    Route::get('/budgets', fn() => response()->json(\App\Models\BudgetPlan::paginate(15)));
    Route::post('/budgets', function (Request $r) {
        $data = $r->validate([
            'budget_name' => 'required|string|max:255', 'fiscal_year' => 'required|string|max:20',
            'budget_category' => 'required|string|max:255', 'allocated_amount' => 'required|numeric|min:0.01',
            'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,closed,draft',
        ]);
        return response()->json(\App\Models\BudgetPlan::create($data + ['created_by' => auth()->id()]), 201);
    });
    Route::get('/revenues', fn() => response()->json(\App\Models\Payment::with('student')->paginate(15)));
    Route::post('/revenues', function (Request $r) {
        $data = $r->validate([
            'student_id' => 'required|exists:students,id', 'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50', 'payment_date' => 'required|date|before_or_equal:today',
        ]);
        return response()->json(\App\Models\Payment::create($data + ['status' => 'pending', 'verification_status' => 'pending']), 201);
    });
    Route::get('/expenses', fn() => response()->json(\App\Models\Expense::paginate(15)));
    Route::post('/expenses', function (Request $r) {
        $data = $r->validate([
            'expense_category' => 'required|string|max:100', 'payee' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01', 'expense_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'required|string|max:100|unique:expenses,reference_number',
        ]);
        return response()->json(\App\Models\Expense::create($data + ['created_by' => auth()->id()]), 201);
    });
    Route::get('/accounts-receivable', fn() => response()->json(\App\Models\StudentAccount::with('student')->paginate(15)));
    Route::get('/accounts-payable', fn() => response()->json(\App\Models\AccountsPayable::paginate(15)));
    Route::get('/funds', fn() => response()->json(\App\Models\Fund::paginate(15)));
    Route::get('/procurement', fn() => response()->json(\App\Models\ProcurementRequest::paginate(15)));
    Route::get('/assets', fn() => response()->json(\App\Models\Asset::paginate(15)));
    Route::get('/reports', function () {
        if (session('reports.unlocked') !== true) {
            return response()->json(['message' => 'Reports access requires password verification via the web interface.'], 403);
        }
        return response()->json(['reports' => ['revenue', 'expenses', 'receivables', 'payables', 'budgets', 'assets', 'executive']]);
    });
    Route::get('/audit-logs', fn() => response()->json(\App\Models\AuditLog::with('user')->latest()->paginate(20)));
});
