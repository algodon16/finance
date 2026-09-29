<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\FundAllocation;
use App\Models\Payment;
use App\Models\ReconciliationRecord;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    protected function pendingQuery(string $statusField = 'status')
    {
        return fn($q) => $q->whereIn($statusField, ['submitted', 'under_review']);
    }

    public function pending(Request $request)
    {
        $type = $request->input('type');
        $budgets = BudgetPlan::whereIn('status', ['submitted', 'under_review'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Budget Plan', 'budget', $r->id, $r->budget_name, $r->allocated_amount, $r->created_by, $r->submitted_at, $r->status, 'accountant.budgets.show', $r->id));
        $allocations = FundAllocation::whereIn('status', ['submitted', 'under_review'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Fund Allocation', 'allocation', $r->id, $r->allocated_to.' — '.$r->purpose, $r->amount, $r->created_by, $r->submitted_at, $r->status, 'accountant.fund-allocations.show', $r->id));
        $expenses = Expense::whereIn('approval_status', ['submitted', 'under_review'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Expense Proposal', 'expense', $r->id, $r->reference_number.' — '.$r->payee, $r->amount, $r->created_by, $r->submitted_at, $r->approval_status, 'accountant.expenses.show', $r->id));
        $payables = AccountsPayable::whereIn('approval_status', ['submitted', 'under_review'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Accounts Payable', 'payable', $r->id, $r->invoice_number.' — '.$r->vendor, $r->amount, $r->created_by, $r->submitted_at, $r->approval_status, 'accountant.payables.show', $r->id));
        $requests = FinancialRequest::whereIn('status', ['submitted', 'under_review'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Financial Request ('.$r->request_type.')', 'request', $r->id, $r->request_number, $r->amount, $r->prepared_by, $r->submitted_at, $r->status, 'accountant.financial-requests.show', $r->id));
        $recons = ReconciliationRecord::whereIn('status', ['submitted'])->orderByDesc('submitted_at')->get()
            ->map(fn($r) => $this->row('Reconciliation', 'reconciliation', $r->id, $r->reference_number, $r->actual_amount, $r->prepared_by, $r->submitted_at, $r->status, 'accountant.reconciliation-records.show', $r->id));

        $items = collect()->concat([$budgets, $allocations, $expenses, $payables, $requests, $recons])->flatten(1)
            ->sortByDesc('submitted_at');
        if ($type) $items = $items->where('kind', $type)->values();

        return view('accountant.submissions.pending', ['items' => $items, 'type' => $type]);
    }

    public function rejected()
    {
        $budgets = BudgetPlan::whereIn('status', ['for_revision', 'revision', 'rejected'])->orderByDesc('reviewed_at')->get()
            ->map(fn($r) => $this->rejectedRow('Budget Proposal', $r->id, $r->budget_name, $r->allocated_amount, $r->submitted_at, $r->reviewed_at, $r->rejection_reason, $r->admin_remarks, $r->status, 'accountant.budgets.show', $r->id));
        $allocations = FundAllocation::whereIn('status', ['for_revision', 'revision', 'rejected'])->orderByDesc('reviewed_at')->get()
            ->map(fn($r) => $this->rejectedRow('Fund Allocation', $r->id, $r->allocated_to, $r->amount, $r->submitted_at, $r->reviewed_at, $r->rejection_reason, $r->admin_remarks, $r->status, 'accountant.fund-allocations.show', $r->id));
        $expenses = Expense::whereIn('approval_status', ['for_revision', 'revision', 'rejected'])->orderByDesc('reviewed_at')->get()
            ->map(fn($r) => $this->rejectedRow('Expense Proposal', $r->id, $r->reference_number, $r->amount, $r->submitted_at, $r->reviewed_at, $r->rejection_reason, $r->admin_remarks, $r->approval_status, 'accountant.expenses.show', $r->id));
        $payables = AccountsPayable::whereIn('approval_status', ['for_revision', 'revision', 'rejected'])->orderByDesc('reviewed_at')->get()
            ->map(fn($r) => $this->rejectedRow('Accounts Payable', $r->id, $r->invoice_number, $r->amount, $r->submitted_at, $r->reviewed_at, $r->rejection_reason, $r->admin_remarks, $r->approval_status, 'accountant.payables.show', $r->id));
        $requests = FinancialRequest::whereIn('status', ['for_revision', 'revision', 'rejected'])->orderByDesc('updated_at')->get()
            ->map(fn($r) => $this->rejectedRow('Financial Request ('.$r->request_type.')', $r->id, $r->request_number, $r->amount, $r->submitted_at, $r->decided_at, $r->admin_decision, $r->admin_remarks, $r->status, 'accountant.financial-requests.show', $r->id));

        $items = collect()->concat([$budgets, $allocations, $expenses, $payables, $requests])->flatten(1)
            ->sortByDesc('reviewed_at');

        return view('accountant.submissions.rejected', ['items' => $items]);
    }

    public function approved()
    {
        $budgets = BudgetPlan::whereIn('status', ['approved', 'active'])->orderByDesc('approved_at')->get()
            ->map(fn($r) => $this->approvedRow('Budget Plan', 'BUD-'.$r->id, $r->budget_name, $r->allocated_amount, (float) $r->utilized_amount, (float) $r->remaining_amount, $r->approved_by, $r->approved_at, $r->status, 'accountant.budgets.show', $r->id));
        $allocations = FundAllocation::where('status', 'approved')->orderByDesc('approved_at')->get()
            ->map(fn($r) => $this->approvedRow('Fund Allocation', 'ALLOC-'.$r->id, $r->allocated_to, $r->amount, null, null, $r->approved_by, $r->approved_at, $r->status, 'accountant.fund-allocations.show', $r->id));
        $expenses = Expense::where('approval_status', 'approved')->orderByDesc('approved_at')->get()
            ->map(fn($r) => $this->approvedRow('Expense Proposal', $r->reference_number, $r->payee, $r->amount, null, null, $r->approved_by, $r->approved_at, $r->approval_status, 'accountant.expenses.show', $r->id));
        $payables = AccountsPayable::where('approval_status', 'approved')->orderByDesc('approved_at')->get()
            ->map(fn($r) => $this->approvedRow('Accounts Payable', $r->invoice_number, $r->vendor, $r->amount, (float) $r->amount_paid, (float) $r->remaining_balance, $r->approved_by, $r->approved_at, $r->approval_status, 'accountant.payables.show', $r->id));
        $requests = FinancialRequest::whereIn('status', ['approved', 'completed'])->orderByDesc('decided_at')->get()
            ->map(fn($r) => $this->approvedRow('Financial Request ('.$r->request_type.')', $r->request_number, $r->description, $r->amount, null, null, $r->decided_by, $r->decided_at, $r->status, 'accountant.financial-requests.show', $r->id));

        $items = collect()->concat([$budgets, $allocations, $expenses, $payables, $requests])->flatten(1)
            ->sortByDesc('approved_at');

        return view('accountant.submissions.approved', ['items' => $items]);
    }

    protected function row($type, $kind, $id, $desc, $amount, $by, $submitted, $status, $route, $param): array
    {
        $days = $submitted ? now()->diffInDays($submitted) : 0;
        $ref = match ($kind) {
            'budget' => 'BUD-'.$id, 'allocation' => 'ALLOC-'.$id, 'expense' => $desc,
            'payable' => $desc, 'request' => $desc, default => '#'.$id,
        };
        return ['type' => $type, 'kind' => $kind, 'ref' => $ref, 'id' => $id, 'desc' => $desc, 'amount' => $amount, 'by' => $by ? (\App\Models\User::find($by)?->name ?? '#'.$by) : '—', 'submitted' => $submitted, 'status' => $status, 'route' => $route, 'param' => $param, 'days' => $days];
    }

    protected function rejectedRow($type, $id, $desc, $amount, $submitted, $reviewed, $reason, $remarks, $status, $route, $param): array
    {
        return ['type' => $type, 'ref' => '#'.$id, 'id' => $id, 'desc' => $desc, 'amount' => $amount, 'submitted' => $submitted, 'reviewed_at' => $reviewed, 'reason' => $reason, 'remarks' => $remarks, 'status' => $status, 'route' => $route, 'param' => $param];
    }

    protected function approvedRow($type, $ref, $desc, $amount, $used, $remaining, $by, $approved, $status, $route, $param): array
    {
        return ['type' => $type, 'ref' => $ref, 'desc' => $desc, 'amount' => $amount, 'used' => $used, 'remaining' => $remaining, 'by' => $by ? (\App\Models\User::find($by)?->name ?? '#'.$by) : '—', 'approved_at' => $approved, 'status' => $status, 'route' => $route, 'param' => $param];
    }
}
