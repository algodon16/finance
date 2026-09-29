<?php

namespace App\Services;

use App\Models\AccountsPayable;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\PayablePayment;
use Illuminate\Support\Facades\DB;

/**
 * AP → Expense/Disbursement integration (server-side, no frontend dependency).
 *
 * One continuous transaction: Request → AP → Disbursement → Payment.
 * Exactly ONE disbursement per approved AP (unique FK), budget committed
 * exactly once (on AP approval), fund moved exactly once per payment.
 */
class ApDisbursementService
{
    /**
     * Create the disbursement record for an approved AP. Idempotent —
     * returns the existing record when one is already linked.
     */
    public static function generateFor(AccountsPayable $ap): Expense
    {
        abort_if(($ap->approval_status ?? 'draft') !== 'approved', 422, 'Disbursement can only be generated for an approved AP.');
        $existing = Expense::where('related_payable_id', $ap->id)->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($ap) {
            // Re-check inside the lock so concurrent approvals cannot duplicate.
            $existing = Expense::where('related_payable_id', $ap->id)->lockForUpdate()->first();
            if ($existing) return $existing;

            $ap->loadMissing(['expense', 'financialRequest', 'budgetPlan']);
            $department = $ap->expense->department ?? $ap->financialRequest->department ?? null;

            $record = Expense::create([
                'reference_number' => 'ED-'.now()->format('Ymd').'-'.strtoupper(uniqid()),
                'expense_category' => $ap->category ?: 'Accounts Payable',
                'department' => $department,
                'payee' => $ap->vendor,
                'amount' => $ap->amount,
                'expense_date' => optional($ap->invoice_date)->toDateString() ?? today()->toDateString(),
                'proposed_payment_date' => optional($ap->due_date)->toDateString(),
                'description' => 'Disbursement for '.$ap->ap_number.' (Invoice '.$ap->invoice_number.')'
                    .($ap->description ? ' — '.$ap->description : ''),
                'supporting_document' => $ap->supporting_document,
                'approval_status' => 'approved',
                'payment_status' => 'for_disbursement',
                'budget_plan_id' => $ap->budget_plan_id,
                'fund_id' => $ap->fund_id,
                'fund_allocation_id' => $ap->fund_allocation_id,
                'related_payable_id' => $ap->id,
                'approved_by' => $ap->approved_by ?? auth()->id(),
                'approved_at' => $ap->approved_at ?? now(),
                'reviewed_by' => $ap->reviewed_by ?? $ap->approved_by ?? auth()->id(),
                'reviewed_at' => $ap->reviewed_at ?? now(),
                'created_by' => auth()->id(),
            ]);

            AuditService::log('auto_generate', 'expenses', (string) $record->id, null, null,
                'System auto-generated Disbursement '.$record->reference_number.' from approved '.$ap->ap_number.' (₱'.number_format($record->amount, 2).'). No manual entry.');
            AuditService::log('disburse', 'accounts_payable', (string) $ap->id, ['status' => 'approved'], ['status' => 'approved'],
                'Approved '.$ap->ap_number.' forwarded to Expense & Disbursement Tracking as '.$record->reference_number.'.');
            WorkflowService::notifyUser($ap->created_by,
                $ap->ap_number.' is now For Disbursement.',
                'Disbursement '.$record->reference_number.' was auto-generated. Open Expense & Disbursement Tracking to settle it.');

            return $record;
        });
    }

    /** Commit the AP amount against its budget — exactly once, at approval time. */
    public static function commitBudget(AccountsPayable $ap): void
    {
        if (! $ap->budget_plan_id) return;
        $budget = BudgetPlan::lockForUpdate()->findOrFail($ap->budget_plan_id);
        abort_if(! in_array($budget->status, ['approved', 'active'], true), 422, 'Linked budget is not approved/active.');
        $remaining = (float) bcsub((string) $budget->allocated_amount, (string) $budget->utilized_amount, 2);
        abort_if((float) $ap->amount > $remaining, 422, 'AP exceeds remaining budget of ₱'.number_format($remaining, 2).'.');
        $budget->increment('utilized_amount', $ap->amount);
    }

    /** Release a previously committed budget (AP destroyed after approval). */
    public static function releaseBudget(AccountsPayable $ap): void
    {
        if (! $ap->budget_plan_id) return;
        $budget = BudgetPlan::lockForUpdate()->find($ap->budget_plan_id);
        if (! $budget) return;
        $new = max(0.0, (float) $budget->utilized_amount - (float) $ap->amount);
        $budget->update(['utilized_amount' => $new]);
    }

    /** Move fund outflow exactly once per payment (guarded by balance check). */
    public static function moveFund(?int $fundId, float $amount, string $reference, string $description): void
    {
        if (! $fundId || $amount <= 0) return;
        $fund = Fund::lockForUpdate()->find($fundId);
        if (! $fund) return;
        abort_if((float) $fund->current_balance < $amount, 422, 'Insufficient fund balance in '.$fund->fund_name.'.');
        $fund->decrement('current_balance', $amount);
        FundTransaction::create([
            'fund_id' => $fund->id, 'transaction_type' => 'outflow',
            'amount' => $amount, 'transaction_date' => today(),
            'reference_number' => $reference, 'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Mirror an AP-side payment onto the linked disbursement.
     * Called after PayablePayment is posted (AP pay flow).
     */
    public static function syncFromPayablePayment(AccountsPayable $ap): void
    {
        $ap->refresh();
        $expense = Expense::where('related_payable_id', $ap->id)->first();
        if (! $expense) return;
        if (in_array($expense->payment_status, ['paid'], true) && (float) $ap->remaining_balance <= 0) return;

        $latest = PayablePayment::where('accounts_payable_id', $ap->id)->latest('id')->first();
        $patch = [
            'payment_method' => $latest->payment_method ?? $expense->payment_method,
            'payment_reference' => $latest->reference_number ?? $expense->payment_reference,
            'payment_date' => $latest->payment_date ?? $expense->payment_date,
        ];
        if ((float) $ap->remaining_balance <= 0) {
            $patch += ['payment_status' => 'paid', 'paid_by' => auth()->id(), 'paid_at' => now()];
            $expense->update($patch);
            AuditService::log('pay', 'expenses', (string) $expense->id, null, null,
                'Disbursement '.$expense->reference_number.' marked Paid via '.$ap->ap_number.' payment of ₱'.number_format($latest->amount ?? $expense->amount, 2).'.');
        } elseif ((float) $ap->amount_paid > 0) {
            $patch += ['payment_status' => 'partially_paid'];
            $expense->update($patch);
        }
    }

    /**
     * Settle the remaining balance from the Expense side (admin Expense pay flow).
     * Creates the PayablePayment, moves fund once, marks both records Paid.
     */
    public static function payViaExpense(Expense $expense, array $payment): void
    {
        abort_if($expense->approval_status !== 'approved', 422, 'Only approved disbursements can be paid.');
        abort_if(in_array($expense->payment_status, ['paid'], true), 422, 'This disbursement is already paid.');

        DB::transaction(function () use ($expense, $payment) {
            $ap = AccountsPayable::lockForUpdate()->findOrFail($expense->related_payable_id);
            $remaining = (float) $ap->remaining_balance;
            abort_if($remaining <= 0, 422, 'The linked AP is already fully paid.');

            $pay = PayablePayment::create([
                'accounts_payable_id' => $ap->id,
                'amount' => $remaining,
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method'] ?? null,
                'reference_number' => $payment['payment_reference'] ?? null,
                'remarks' => 'Settled via disbursement '.$expense->reference_number.'.',
                'created_by' => auth()->id(),
            ]);
            $ap->increment('amount_paid', $remaining);
            $ap->refresh();
            (new \App\Http\Controllers\Admin\Fms\PayableController())->syncStatusPublic($ap);

            self::moveFund($ap->fund_id ?? $expense->fund_id, $remaining, $ap->invoice_number,
                'Disbursement '.$expense->reference_number.' for '.$ap->ap_number);

            $expense->update([
                'payment_status' => 'paid',
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method'] ?? $expense->payment_method,
                'payment_reference' => $payment['payment_reference'] ?? $expense->payment_reference,
                'proof_of_payment' => $payment['proof_of_payment'] ?? $expense->proof_of_payment,
                'paid_by' => auth()->id(), 'paid_at' => now(),
            ]);

            AuditService::log('pay', 'expenses', (string) $expense->id, ['payment_status' => 'for_disbursement'], ['payment_status' => 'paid'],
                'Admin '.auth()->user()->name.' settled Disbursement '.$expense->reference_number.' (₱'.number_format($remaining, 2).' via '.($payment['payment_method'] ?? '—').').');
            AuditService::log('pay', 'accounts_payable', (string) $ap->id, null, null,
                'Admin '.auth()->user()->name.' posted ₱'.number_format($remaining, 2).' payment to '.$ap->ap_number.' via disbursement (Ref: '.($pay->reference_number ?? '—').').');
        });
    }

    /** Cancel a pending disbursement when its AP is destroyed/cancelled. */
    public static function cancelFor(AccountsPayable $ap, string $reason): void
    {
        $expense = Expense::where('related_payable_id', $ap->id)->first();
        if (! $expense || $expense->payment_status === 'paid') return;
        $expense->update([
            'approval_status' => 'cancelled',
            'admin_remarks' => trim(($expense->admin_remarks ? $expense->admin_remarks.' | ' : '').'AP '.$reason),
        ]);
        AuditService::log('cancel', 'expenses', (string) $expense->id, null, ['status' => 'cancelled'],
            'Disbursement '.$expense->reference_number.' cancelled because its AP was '.$reason.'.');
    }
}
