<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Student\PaymentController as StudentPayment;
use App\Http\Controllers\Student\BudgetPlannerController;
use App\Http\Controllers\Student\ProcurementController as StudentProcurement;
use App\Http\Controllers\Student\LearningMaterialController as StudentMaterials;
use App\Http\Controllers\Student\ClearanceController as StudentClearance;
use App\Http\Controllers\Student\SettingController as StudentSetting;
use App\Http\Controllers\Student\NotificationController as StudentNotification;
use App\Http\Controllers\Admin\NotificationController as AdminNotification;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboard;
use App\Http\Controllers\Cashier\PaymentVerificationController;
use App\Http\Controllers\Cashier\WalkInPaymentController;
use App\Http\Controllers\Cashier\ReportController as CashierReport;
use App\Http\Controllers\Accountant\DashboardController as AccountantDashboard;
use App\Http\Controllers\Accountant\PaymentRecordController as AccountantPaymentRecord;
use App\Http\Controllers\Accountant\ReconciliationController as AccountantReconciliation;
use App\Http\Controllers\Accountant\AccountReceivableController;
use App\Http\Controllers\Accountant\FinancialReportController as AccountantFinancialReport;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\StudentController as AdminStudent;
use App\Http\Controllers\Admin\ProcurementItemController;
use App\Http\Controllers\Admin\FeeAssessmentController;
use App\Http\Controllers\Admin\AssessmentRuleController;
use App\Http\Controllers\Admin\ReportController as AdminReport;
use App\Http\Controllers\ReportController;

// Auth Routes
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Two-factor OTP challenge (guest with pending session only; never exposes the code)
Route::get('/otp/email', [\App\Http\Controllers\OtpController::class, 'showEmail'])->name('otp.email');
Route::post('/otp/email/verify', [\App\Http\Controllers\OtpController::class, 'verifyEmail'])->middleware('throttle:10,1')->name('otp.email.verify');
Route::post('/otp/email/resend', [\App\Http\Controllers\OtpController::class, 'resendEmail'])->middleware('throttle:5,1')->name('otp.email.resend');
Route::post('/otp/phone/send', [\App\Http\Controllers\OtpController::class, 'sendPhone'])->middleware('throttle:5,1')->name('otp.phone.send');
Route::get('/otp/phone', [\App\Http\Controllers\OtpController::class, 'showPhone'])->name('otp.phone');
Route::post('/otp/phone/verify', [\App\Http\Controllers\OtpController::class, 'verifyPhone'])->middleware('throttle:10,1')->name('otp.phone.verify');
Route::post('/otp/phone/resend', [\App\Http\Controllers\OtpController::class, 'resendPhone'])->middleware('throttle:5,1')->name('otp.phone.resend');
Route::post('/otp/back-to-email', [\App\Http\Controllers\OtpController::class, 'backToEmail'])->middleware('throttle:10,1')->name('otp.back-email');

// Authenticated Routes (OTP-verified + 5-minute inactivity timeout on every module)
Route::middleware(['auth', 'otp.verified', 'session.timeout'])->group(function () {

    // Notifications
    Route::get('/notifications', [StudentNotification::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [StudentNotification::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [StudentNotification::class, 'markAllRead'])->name('notifications.readAll');

    // Student Routes
    Route::middleware(['role:student'])->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentDashboard::class, 'index'])->name('dashboard');

        // Payments
        Route::get('/payments', [StudentPayment::class, 'index'])->name('payments.index');
        Route::get('/payments/create', [StudentPayment::class, 'create'])->name('payments.create');
        Route::post('/payments', [StudentPayment::class, 'store'])->name('payments.store');
        Route::get('/payments/{id}/edit', [StudentPayment::class, 'edit'])->name('payments.edit');
        Route::put('/payments/{id}', [StudentPayment::class, 'update'])->name('payments.update');
        Route::get('/payments/{id}/proof/{proofId}', [StudentPayment::class, 'proof'])->name('payments.proof');
        Route::get('/payments/{id}', [StudentPayment::class, 'show'])->name('payments.show');

        // Budget Planner
        Route::get('/budget-planner', [BudgetPlannerController::class, 'index'])->name('budget.index');
        Route::post('/budget-planner/calculate', [BudgetPlannerController::class, 'calculate'])->name('budget.calculate');

        // Procurement (static paths first so they are never captured by {id})
        Route::get('/procurement', [StudentProcurement::class, 'index'])->name('procurement.index');
        Route::get('/procurement/learning-materials', [StudentMaterials::class, 'index'])->name('procurement.materials.index');
        Route::post('/procurement/learning-materials/cart', [StudentMaterials::class, 'addToCart'])->name('procurement.materials.cart.add');
        Route::post('/procurement/learning-materials/cart/remove', [StudentMaterials::class, 'removeFromCart'])->name('procurement.materials.cart.remove');
        Route::post('/procurement/learning-materials/submit', [StudentMaterials::class, 'submit'])->name('procurement.materials.submit');
        Route::get('/procurement/requests/{id}', [StudentProcurement::class, 'showRequest'])->name('procurement.request.show')->whereNumber('id');
        Route::get('/procurement/{id}', [StudentProcurement::class, 'show'])->name('procurement.show')->whereNumber('id');
        Route::post('/procurement/request', [StudentProcurement::class, 'submitRequest'])->name('procurement.request');

        // Clearance
        Route::get('/clearance', [StudentClearance::class, 'index'])->name('clearance');

        // Settings (email + password only; all other info is read-only)
        Route::get('/settings', [StudentSetting::class, 'index'])->name('settings.index');
        Route::put('/settings/email', [StudentSetting::class, 'updateEmail'])->name('settings.email.update');
        Route::put('/settings/password', [StudentSetting::class, 'updatePassword'])->name('settings.password.update');
    });

    // Cashier Routes
    Route::middleware(['role:cashier'])->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/dashboard', [CashierDashboard::class, 'index'])->name('dashboard');

        // Payment (walk-in counter payments, cash only)
        Route::get('/payment', [WalkInPaymentController::class, 'index'])->name('payment.index');
        Route::get('/payment/lookup', [WalkInPaymentController::class, 'lookup'])->name('payment.lookup');
        Route::post('/payment', [WalkInPaymentController::class, 'store'])->name('payment.store');
        Route::get('/payment/success/{payment}', [WalkInPaymentController::class, 'success'])->name('payment.success');

        // Payment Verification
        Route::get('/payments', [PaymentVerificationController::class, 'index'])->name('payments.index');
        Route::get('/payments/{id}', [PaymentVerificationController::class, 'show'])->name('payments.show');
        Route::post('/payments/{id}/approve', [PaymentVerificationController::class, 'approve'])->name('payments.approve');
        Route::post('/payments/{id}/reject', [PaymentVerificationController::class, 'reject'])->name('payments.reject');

        // Reports
        Route::get('/reports', [CashierReport::class, 'index'])->name('reports.index');
        Route::get('/reports/daily', [CashierReport::class, 'dailyCollections'])->name('reports.daily');
        Route::get('/reports/summary', [CashierReport::class, 'summary'])->name('reports.summary');
    });

    // Accountant Routes (financial monitoring only; no cashier operations)
    Route::middleware(['role:accountant'])->prefix('accountant')->name('accountant.')->group(function () {
        Route::get('/dashboard', [AccountantDashboard::class, 'index'])->name('dashboard');

        // Payment Records (view-only financial transaction records)
        Route::get('/payment-records', [AccountantPaymentRecord::class, 'index'])->name('payment-records.index');
        Route::get('/payment-records/{id}', [AccountantPaymentRecord::class, 'show'])->name('payment-records.show');

        // Reconciliation (system vs actual financial records)
        Route::get('/reconciliation', [AccountantReconciliation::class, 'index'])->name('reconciliation.index');
        Route::get('/reconciliation/{id}', [AccountantReconciliation::class, 'show'])->name('reconciliation.show');

        // Accounts Receivable (outstanding student balances)
        Route::get('/accounts-receivable', [AccountReceivableController::class, 'index'])->name('accounts-receivable.index');
        Route::get('/accounts-receivable/{id}', [AccountReceivableController::class, 'show'])->name('accounts-receivable.show');

        // Financial Reports
        Route::get('/financial-reports', [AccountantFinancialReport::class, 'index'])->name('financial-reports.index');
    });

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

        // Student Management
        Route::get('/students', [AdminStudent::class, 'index'])->name('students.index');
        Route::get('/students/create', [AdminStudent::class, 'create'])->name('students.create');
        Route::post('/students', [AdminStudent::class, 'store'])->name('students.store');
        Route::get('/students/{id}', [AdminStudent::class, 'show'])->name('students.show');
        Route::get('/students/{id}/edit', [AdminStudent::class, 'edit'])->name('students.edit');
        Route::put('/students/{id}', [AdminStudent::class, 'update'])->name('students.update');

        // Payments (all payments overview, reuses Payment model)
        Route::get('/payments', [AdminReport::class, 'payments'])->name('payments.index');

        // Procurement Items
        Route::get('/procurement-items', [ProcurementItemController::class, 'index'])->name('procurementItems.index');
        Route::get('/procurement-items/create', [ProcurementItemController::class, 'create'])->name('procurementItems.create');
        Route::post('/procurement-items', [ProcurementItemController::class, 'store'])->name('procurementItems.store');
        Route::get('/procurement-items/{id}/edit', [ProcurementItemController::class, 'edit'])->name('procurementItems.edit');
        Route::put('/procurement-items/{id}', [ProcurementItemController::class, 'update'])->name('procurementItems.update');
        Route::delete('/procurement-items/{id}', [ProcurementItemController::class, 'destroy'])->name('procurementItems.destroy');

        // Fee Assessment (admin-configured fee catalog)
        Route::get('/fee-assessment', [FeeAssessmentController::class, 'index'])->name('feeAssessment.index');
        Route::get('/fee-assessment/create', [FeeAssessmentController::class, 'create'])->name('feeAssessment.create');
        Route::post('/fee-assessment', [FeeAssessmentController::class, 'store'])->name('feeAssessment.store');
        Route::get('/fee-assessment/{id}/edit', [FeeAssessmentController::class, 'edit'])->name('feeAssessment.edit');
        Route::put('/fee-assessment/{id}', [FeeAssessmentController::class, 'update'])->name('feeAssessment.update');
        Route::delete('/fee-assessment/{id}', [FeeAssessmentController::class, 'destroy'])->name('feeAssessment.destroy');

        // Assessment Rules (program/year/semester applicability for catalog fees)
        Route::get('/assessment-rules', [AssessmentRuleController::class, 'index'])->name('assessmentRules.index');
        Route::get('/assessment-rules/create', [AssessmentRuleController::class, 'create'])->name('assessmentRules.create');
        Route::post('/assessment-rules', [AssessmentRuleController::class, 'store'])->name('assessmentRules.store');
        Route::get('/assessment-rules/{id}/edit', [AssessmentRuleController::class, 'edit'])->name('assessmentRules.edit');
        Route::put('/assessment-rules/{id}', [AssessmentRuleController::class, 'update'])->name('assessmentRules.update');
        Route::delete('/assessment-rules/{id}', [AssessmentRuleController::class, 'destroy'])->name('assessmentRules.destroy');

        // Notifications (automatic payment reminders + history)
        Route::get('/notifications', [AdminNotification::class, 'index'])->name('notifications.index');
        Route::put('/notifications/settings', [AdminNotification::class, 'updateSettings'])->name('notifications.settings.update');

        // ---- BCP Financial Management System (admin-only modules) ----
        Route::get('/budgets/ai', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'ai'])->name('budgets.ai');
        Route::post('/budgets/ai', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'aiRecommend'])->name('budgets.ai.calculate');
        Route::get('/budgets', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/create', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'store'])->name('budgets.store');
        Route::get('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'show'])->name('budgets.show');
        Route::get('/budgets/{budget}/edit', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'edit'])->name('budgets.edit');
        Route::put('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'update'])->name('budgets.update');
        Route::delete('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'destroy'])->name('budgets.destroy');
        Route::post('/budgets/{budget}/allocate', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'allocate'])->name('budgets.allocate');
        Route::delete('/budgets/{budget}/allocations/{allocation}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'destroyAllocation'])->name('budgets.allocations.destroy');

        Route::get('/revenues/export', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'export'])->name('revenues.export');
        Route::get('/revenues', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'index'])->name('revenues.index');
        Route::get('/revenues/create', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'create'])->name('revenues.create');
        Route::post('/revenues', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'store'])->name('revenues.store');
        Route::get('/revenues/{revenue}', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'show'])->name('revenues.show');
        Route::get('/revenues/{revenue}/edit', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'edit'])->name('revenues.edit');
        Route::put('/revenues/{revenue}', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'update'])->name('revenues.update');
        Route::post('/revenues/{revenue}/verify', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'setStatus'])->defaults('action', 'verify')->name('revenues.verify');
        Route::post('/revenues/{revenue}/reject', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'setStatus'])->defaults('action', 'reject')->name('revenues.reject');
        Route::post('/revenues/{revenue}/reconcile', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'setStatus'])->defaults('action', 'reconcile')->name('revenues.reconcile');

        Route::get('/expenses', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'show'])->name('expenses.show');
        Route::get('/expenses/{expense}/edit', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/expenses/{expense}/approve', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'setStatus'])->defaults('action', 'approve')->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'setStatus'])->defaults('action', 'reject')->name('expenses.reject');
        Route::post('/expenses/{expense}/pay', [\App\Http\Controllers\Admin\Fms\ExpenseController::class, 'setStatus'])->defaults('action', 'pay')->name('expenses.pay');

        Route::get('/payables', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'index'])->name('payables.index');
        Route::get('/payables/create', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'create'])->name('payables.create');
        Route::post('/payables', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'store'])->name('payables.store');
        Route::get('/payables/{payable}', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'show'])->name('payables.show');
        Route::get('/payables/{payable}/edit', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'edit'])->name('payables.edit');
        Route::put('/payables/{payable}', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'update'])->name('payables.update');
        Route::delete('/payables/{payable}', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'destroy'])->name('payables.destroy');
        Route::post('/payables/{payable}/pay', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'pay'])->name('payables.pay');

        Route::get('/receivables/assess', [\App\Http\Controllers\Admin\Fms\ReceivableController::class, 'assess'])->name('receivables.assess');
        Route::post('/receivables/assess', [\App\Http\Controllers\Admin\Fms\ReceivableController::class, 'storeAssessment'])->name('receivables.assess.store');
        Route::get('/receivables', [\App\Http\Controllers\Admin\Fms\ReceivableController::class, 'index'])->name('receivables.index');
        Route::get('/receivables/{receivable}', [\App\Http\Controllers\Admin\Fms\ReceivableController::class, 'show'])->name('receivables.show');

        Route::get('/funds', [\App\Http\Controllers\Admin\Fms\FundController::class, 'index'])->name('funds.index');
        Route::get('/funds/create', [\App\Http\Controllers\Admin\Fms\FundController::class, 'create'])->name('funds.create');
        Route::post('/funds', [\App\Http\Controllers\Admin\Fms\FundController::class, 'store'])->name('funds.store');
        Route::get('/funds/{fund}', [\App\Http\Controllers\Admin\Fms\FundController::class, 'show'])->name('funds.show');
        Route::get('/funds/{fund}/edit', [\App\Http\Controllers\Admin\Fms\FundController::class, 'edit'])->name('funds.edit');
        Route::put('/funds/{fund}', [\App\Http\Controllers\Admin\Fms\FundController::class, 'update'])->name('funds.update');
        Route::post('/funds/{fund}/transactions', [\App\Http\Controllers\Admin\Fms\FundController::class, 'transaction'])->name('funds.transactions');
        Route::post('/funds/{fund}/allocate', [\App\Http\Controllers\Admin\Fms\FundController::class, 'allocate'])->name('funds.allocate');

        Route::get('/procurement', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'index'])->name('procurement.index');
        Route::get('/procurement/create', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'create'])->name('procurement.create');
        Route::post('/procurement', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'store'])->name('procurement.store');
        Route::get('/procurement/{procurement}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'show'])->name('procurement.show');
        Route::get('/procurement/{procurement}/edit', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'edit'])->name('procurement.edit');
        Route::put('/procurement/{procurement}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'update'])->name('procurement.update');
        Route::post('/procurement/{procurement}/{action}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'setStatus'])->name('procurement.status');

        Route::get('/assets', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/create', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'show'])->name('assets.show');
        Route::get('/assets/{asset}/edit', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'destroy'])->name('assets.destroy');

        Route::post('/reports/verify', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'verifyAccess'])->middleware('throttle:10,1')->name('reports.verify');

        Route::middleware(['reports.unlocked'])->group(function () {
            Route::get('/reports', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/revenue', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('/reports/expenses', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'expenses'])->name('reports.expenses');
            Route::get('/reports/receivables', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'receivables'])->name('reports.receivables');
            Route::get('/reports/payables', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'payables'])->name('reports.payables');
            Route::get('/reports/budgets', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'budgets'])->name('reports.budgets');
            Route::get('/reports/assets', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'assets'])->name('reports.assets');
            Route::get('/reports/executive', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'executive'])->name('reports.executive');
        });

        Route::get('/audit-trail', [\App\Http\Controllers\Admin\Fms\AuditController::class, 'index'])->name('audit.index');

        Route::get('/settings', [\App\Http\Controllers\Admin\Fms\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/profile', [\App\Http\Controllers\Admin\Fms\SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::post('/settings/password', [\App\Http\Controllers\Admin\Fms\SettingsController::class, 'updatePassword'])->name('settings.password');
    });

    // Shared Report Routes
    Route::get('/reports/student/{studentId}/statement', [ReportController::class, 'studentStatement'])->name('reports.studentStatement');
    Route::get('/reports/payments/history', [ReportController::class, 'paymentHistory'])->name('reports.paymentHistory');
});
