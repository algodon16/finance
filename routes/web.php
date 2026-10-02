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

// Authenticated Routes (OTP-verified; session timeout temporarily disabled)
// To re-enable: add 'session.timeout' back to the middleware list below.
Route::middleware(['auth', 'otp.verified'])->group(function () {

    // Keeps the session alive while a tab is left open (pinged by layouts via JS).
    Route::get('/keep-alive', fn() => response()->json(['ok' => true]))->name('keep-alive');

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

    // Accountant Routes — 1:1 mirror of Admin modules (prepare side; no final approval)
    Route::middleware(['role:accountant'])->prefix('accountant')->name('accountant.')->group(function () {
        Route::get('/dashboard', [AccountantDashboard::class, 'index'])->name('dashboard');

        // Revenue Management (same `payments` table as Admin → Revenue Management)
        Route::get('/revenue', [\App\Http\Controllers\Accountant\RevenueController::class, 'index'])->name('revenue.index');
        Route::get('/revenue/create', [\App\Http\Controllers\Accountant\RevenueController::class, 'create'])->name('revenue.create');
        Route::post('/revenue', [\App\Http\Controllers\Accountant\RevenueController::class, 'store'])->name('revenue.store');
        Route::get('/revenue/{revenue}', [\App\Http\Controllers\Accountant\RevenueController::class, 'show'])->name('revenue.show');
        Route::get('/revenue/{revenue}/edit', [\App\Http\Controllers\Accountant\RevenueController::class, 'edit'])->name('revenue.edit');
        Route::put('/revenue/{revenue}', [\App\Http\Controllers\Accountant\RevenueController::class, 'update'])->name('revenue.update');
        Route::post('/revenue/{revenue}/submit', [\App\Http\Controllers\Accountant\RevenueController::class, 'submit'])->name('revenue.submit');
        Route::post('/revenue/{revenue}/cancel', [\App\Http\Controllers\Accountant\RevenueController::class, 'cancel'])->name('revenue.cancel');
        Route::post('/revenue/{revenue}/verify', [\App\Http\Controllers\Accountant\RevenueController::class, 'verify'])->name('revenue.verify');
        Route::post('/revenue/{revenue}/reject-invalid', [\App\Http\Controllers\Accountant\RevenueController::class, 'rejectInvalid'])->name('revenue.rejectInvalid');

        // Legacy Payment Records URLs → Revenue Management (functionality migrated, no data loss)
        Route::match(['GET', 'POST'], '/payment-records', fn() => redirect()->route('accountant.revenue.index', [], 301))->name('payment-records.index');
        Route::match(['GET', 'POST'], '/payment-records/{id}', fn($id) => redirect()->route('accountant.revenue.show', $id, 301))->name('payment-records.show');
        Route::match(['GET', 'POST'], '/payment-records/{id}/verify', fn($id) => redirect()->route('accountant.revenue.show', $id, 301))->name('payment-records.verify');
        Route::match(['GET', 'POST'], '/payment-records/{id}/reject', fn($id) => redirect()->route('accountant.revenue.show', $id, 301))->name('payment-records.reject');
        Route::match(['GET', 'POST'], '/payment-records/{id}/submit', fn($id) => redirect()->route('accountant.revenue.show', $id, 301))->name('payment-records.submit');

        // Financial Reporting and Compliance (reports + reconciliation sub-pages)
        Route::get('/financial-reports', [AccountantFinancialReport::class, 'index'])->name('financial-reports.index');

        // Reconciliation (derived view + prepared records)
        Route::get('/reconciliation', [AccountantReconciliation::class, 'index'])->name('reconciliation.index');
        Route::get('/reconciliation/{id}', [AccountantReconciliation::class, 'show'])->name('reconciliation.show');
        Route::get('/reconciliation-records', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'index'])->name('reconciliation-records.index');
        Route::get('/reconciliation-records/create', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'create'])->name('reconciliation-records.create');
        Route::post('/reconciliation-records', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'store'])->name('reconciliation-records.store');
        Route::get('/reconciliation-records/{reconciliationRecord}', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'show'])->name('reconciliation-records.show');
        Route::get('/reconciliation-records/{reconciliationRecord}/edit', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'edit'])->name('reconciliation-records.edit');
        Route::put('/reconciliation-records/{reconciliationRecord}', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'update'])->name('reconciliation-records.update');
        Route::post('/reconciliation-records/{reconciliationRecord}/submit', [\App\Http\Controllers\Accountant\ReconciliationRecordController::class, 'submit'])->name('reconciliation-records.submit');

        // Accounts Receivable (review balances/ledger; adjustments via financial-requests)
        Route::get('/accounts-receivable', [AccountReceivableController::class, 'index'])->name('accounts-receivable.index');
        Route::get('/accounts-receivable/{id}', [AccountReceivableController::class, 'show'])->name('accounts-receivable.show');

        // Budget Planning (accountant prepares, admin approves)
        // Hub: Overview / Requests / Planning (numeric IDs only — unknown slugs 404 instead of 500)
        Route::get('/budgets/overview', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'overview'])->name('budgets.overview');
        Route::get('/budgets/requests', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'requests'])->name('budgets.requests');
        Route::get('/budgets/planning', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'planning'])->name('budgets.planning');
        Route::get('/budgets/history', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'history'])->name('budgets.history');
        Route::get('/budgets', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/create', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'store'])->name('budgets.store');
        Route::post('/budgets/{budget}/review', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'review'])->name('budgets.review')->whereNumber('budget');
        Route::post('/budgets/{budget}/return-review', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'returnForRevision'])->name('budgets.return-review')->whereNumber('budget');
        Route::get('/budgets/{budget}', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'show'])->name('budgets.show')->whereNumber('budget');
        Route::get('/budgets/{budget}/edit', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'edit'])->name('budgets.edit')->whereNumber('budget');
        Route::put('/budgets/{budget}', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'update'])->name('budgets.update')->whereNumber('budget');
        Route::post('/budgets/{budget}/submit', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'submit'])->name('budgets.submit')->whereNumber('budget');
        Route::post('/budgets/{budget}/cancel', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'cancel'])->name('budgets.cancel')->whereNumber('budget');
        Route::post('/budgets/{budget}/duplicate', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'duplicate'])->name('budgets.duplicate')->whereNumber('budget');
        Route::delete('/budgets/{budget}/draft', [\App\Http\Controllers\Accountant\BudgetPlanController::class, 'destroyDraft'])->name('budgets.draft.destroy')->whereNumber('budget');

        // Fund Allocation
        Route::get('/fund-allocations', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'index'])->name('fund-allocations.index');
        Route::get('/fund-allocations/create', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'create'])->name('fund-allocations.create');
        Route::post('/fund-allocations', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'store'])->name('fund-allocations.store');
        Route::get('/fund-allocations/{fundAllocation}', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'show'])->name('fund-allocations.show');
        Route::get('/fund-allocations/{fundAllocation}/edit', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'edit'])->name('fund-allocations.edit');
        Route::put('/fund-allocations/{fundAllocation}', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'update'])->name('fund-allocations.update');
        Route::post('/fund-allocations/{fundAllocation}/submit', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'submit'])->name('fund-allocations.submit');
        Route::post('/fund-allocations/{fundAllocation}/cancel', [\App\Http\Controllers\Accountant\FundAllocationController::class, 'cancel'])->name('fund-allocations.cancel');

        // Expense and Disbursement Tracking (auto disbursements from approved requests)
        Route::get('/expenses', [\App\Http\Controllers\Accountant\ExpenseProposalController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/{expense}', [\App\Http\Controllers\Accountant\ExpenseProposalController::class, 'show'])->name('expenses.show');
        Route::get('/expenses/{expense}/record-data', [\App\Http\Controllers\Accountant\ExpenseProposalController::class, 'recordData'])->name('expenses.record-data');
        Route::post('/expenses/{expense}/record-actual', [\App\Http\Controllers\Accountant\ExpenseProposalController::class, 'recordActual'])->name('expenses.record-actual');

        // Accounts Payable
        Route::get('/payables', [\App\Http\Controllers\Accountant\PayableController::class, 'index'])->name('payables.index');
        Route::get('/payables/source-preview', [\App\Http\Controllers\Accountant\PayableController::class, 'sourcePreview'])->name('payables.source-preview');
        Route::get('/payables/{payable}', [\App\Http\Controllers\Accountant\PayableController::class, 'show'])->name('payables.show');
        Route::post('/payables/{payable}/pay', [\App\Http\Controllers\Accountant\PayableController::class, 'pay'])->name('payables.pay');
        Route::post('/payables/{payable}/complete', [\App\Http\Controllers\Accountant\PayableController::class, 'complete'])->name('payables.complete');

        // Financial Requests (centralized)
        Route::get('/financial-requests', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'index'])->name('financial-requests.index');
        Route::get('/financial-requests/create', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'create'])->name('financial-requests.create');
        Route::post('/financial-requests', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'store'])->name('financial-requests.store');
        Route::get('/financial-requests/link-preview', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'linkPreview'])->name('financial-requests.link-preview');
        Route::get('/financial-requests/department-budgets', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'departmentBudgets'])->name('financial-requests.department-budgets');
        Route::get('/financial-requests/item-lookup', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'itemLookup'])->name('financial-requests.item-lookup');
        Route::get('/financial-requests/supplier-items', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'supplierItems'])->name('financial-requests.supplier-items');
        Route::get('/financial-requests/{financialRequest}', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'show'])->name('financial-requests.show');
        Route::get('/financial-requests/{financialRequest}/edit', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'edit'])->name('financial-requests.edit');
        Route::put('/financial-requests/{financialRequest}', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'update'])->name('financial-requests.update');
        Route::post('/financial-requests/{financialRequest}/submit', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'submit'])->name('financial-requests.submit');
        Route::post('/financial-requests/{financialRequest}/review', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'review'])->name('financial-requests.review');
        Route::post('/financial-requests/simulate', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'simulate'])->name('financial-requests.simulate');
        Route::post('/financial-requests/{financialRequest}/cancel', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'cancel'])->name('financial-requests.cancel');
        Route::get('/procurement-requests', [\App\Http\Controllers\Accountant\FinancialRequestController::class, 'procurement'])->name('procurement.index');

        // Asset and Depreciation Management (same `assets` table as Admin)
        Route::get('/assets', [\App\Http\Controllers\Accountant\AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/create', [\App\Http\Controllers\Accountant\AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [\App\Http\Controllers\Accountant\AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}', [\App\Http\Controllers\Accountant\AssetController::class, 'show'])->name('assets.show');
        Route::get('/assets/{asset}/edit', [\App\Http\Controllers\Accountant\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [\App\Http\Controllers\Accountant\AssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/{asset}/submit', [\App\Http\Controllers\Accountant\AssetController::class, 'submit'])->name('assets.submit');
        Route::post('/assets/{asset}/cancel', [\App\Http\Controllers\Accountant\AssetController::class, 'cancel'])->name('assets.cancel');

        // Submissions (status/history views only — not sidebar modules, not an approval center)
        Route::get('/submissions/pending', [\App\Http\Controllers\Accountant\SubmissionController::class, 'pending'])->name('submissions.pending');
        Route::get('/submissions/rejected', [\App\Http\Controllers\Accountant\SubmissionController::class, 'rejected'])->name('submissions.rejected');
        Route::get('/submissions/approved', [\App\Http\Controllers\Accountant\SubmissionController::class, 'approved'])->name('submissions.approved');

        // Security and Audit Trail (own activity, read-only)
        Route::get('/audit-trail', [\App\Http\Controllers\Accountant\AuditController::class, 'index'])->name('audit.index');

        // System Settings (own profile only)
        Route::get('/settings', [\App\Http\Controllers\Accountant\SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings/profile', [\App\Http\Controllers\Accountant\SettingController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/settings/password', [\App\Http\Controllers\Accountant\SettingController::class, 'updatePassword'])->name('settings.password');
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
        // Budget Requests inbox (requests forwarded by accountant for admin approval)
        Route::get('/budgets/overview', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'overview'])->name('budgets.overview');
        Route::get('/budgets/requests', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'requests'])->name('budgets.requests');
        Route::get('/budgets/history', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'history'])->name('budgets.history');
        Route::post('/budgets/{budget}/approve-request', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'approveRequest'])->name('budgets.approve-request')->whereNumber('budget');
        Route::post('/budgets/{budget}/reject-request', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'rejectRequest'])->name('budgets.reject-request')->whereNumber('budget');
        Route::post('/budgets/{budget}/return-request', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'returnRequest'])->name('budgets.return-request')->whereNumber('budget');
        Route::get('/budgets', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/create', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'store'])->name('budgets.store');
        Route::get('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'show'])->name('budgets.show')->whereNumber('budget');
        Route::get('/budgets/{budget}/edit', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'edit'])->name('budgets.edit')->whereNumber('budget');
        Route::put('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'update'])->name('budgets.update')->whereNumber('budget');
        Route::delete('/budgets/{budget}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'destroy'])->name('budgets.destroy')->whereNumber('budget');
        Route::post('/budgets/{budget}/allocate', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'allocate'])->name('budgets.allocate')->whereNumber('budget');
        Route::delete('/budgets/{budget}/allocations/{allocation}', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'destroyAllocation'])->name('budgets.allocations.destroy')->whereNumber(['budget', 'allocation']);
        Route::post('/budgets/{budget}/approve', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'approve'])->name('budgets.approve')->whereNumber('budget');
        Route::post('/budgets/{budget}/reject', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'reject'])->name('budgets.reject')->whereNumber('budget');
        Route::post('/budgets/{budget}/for-revision', [\App\Http\Controllers\Admin\Fms\BudgetController::class, 'forRevision'])->name('budgets.for-revision')->whereNumber('budget');

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
        Route::post('/revenues/{revenue}/approve', [\App\Http\Controllers\Admin\Fms\RevenueController::class, 'setStatus'])->defaults('action', 'approve')->name('revenues.approve');

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
        Route::post('/payables/{payable}/approve', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'approve'])->name('payables.approve');
        Route::post('/payables/{payable}/reject', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'reject'])->name('payables.reject');
        Route::post('/payables/{payable}/disbursement', [\App\Http\Controllers\Admin\Fms\PayableController::class, 'generateDisbursement'])->name('payables.disbursement');

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
        Route::delete('/funds/{fund}', [\App\Http\Controllers\Admin\Fms\FundController::class, 'destroy'])->name('funds.destroy');
        Route::post('/funds/{fund}/transactions', [\App\Http\Controllers\Admin\Fms\FundController::class, 'transaction'])->name('funds.transactions');

        Route::get('/procurement', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'index'])->name('procurement.index');
        Route::get('/procurement/create', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'create'])->name('procurement.create');
        Route::post('/procurement', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'store'])->name('procurement.store');
        Route::get('/procurement/{procurement}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'show'])->name('procurement.show');
        Route::get('/procurement/{procurement}/edit', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'edit'])->name('procurement.edit');
        Route::put('/procurement/{procurement}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'update'])->name('procurement.update');
        Route::post('/procurement/{procurement}/{action}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'setStatus'])->name('procurement.status');
        Route::get('/financial-requests', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'financialRequests'])->name('financial-requests.index');
        Route::get('/financial-requests/{request}', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'showFinancialRequest'])->name('financial-requests.show');
        Route::post('/financial-requests/{request}/approve', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'approveFinancialRequest'])->name('financial-requests.approve');
        Route::post('/financial-requests/{request}/reject', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'rejectFinancialRequest'])->name('financial-requests.reject');
        Route::post('/financial-requests/{request}/return', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'returnFinancialRequest'])->name('financial-requests.return');
        Route::post('/financial-requests/simulate', [\App\Http\Controllers\Admin\Fms\ProcurementController::class, 'simulateFinancialRequest'])->name('financial-requests.simulate');

        Route::get('/assets', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/create', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'show'])->name('assets.show');
        Route::get('/assets/{asset}/edit', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/{asset}/approve', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'approve'])->name('assets.approve');
        Route::post('/assets/{asset}/reject', [\App\Http\Controllers\Admin\Fms\AssetController::class, 'reject'])->name('assets.reject');

        Route::post('/reports/verify', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'verifyAccess'])->middleware('throttle:10,1')->name('reports.verify');

        Route::middleware(['reports.unlocked'])->group(function () {
            Route::get('/reports', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/reconciliations', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'reconciliations'])->name('reports.reconciliations');
            Route::post('/reports/reconciliations/{record}/approve', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'approveReconciliation'])->name('reports.reconciliations.approve');
            Route::post('/reports/reconciliations/{record}/reject', [\App\Http\Controllers\Admin\Fms\ReportController::class, 'rejectReconciliation'])->name('reports.reconciliations.reject');
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
