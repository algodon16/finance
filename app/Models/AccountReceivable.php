<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountReceivable extends Model
{
    protected $table = 'accounts_receivable';

    protected $fillable = [
        'reference_number', 'student_id', 'financial_charge_id', 'description',
        'billed_amount', 'paid_amount', 'balance', 'due_date', 'status', 'assessed_by',
    ];

    protected $casts = [
        'billed_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
    ];

    public const STATUSES = ['open', 'partial', 'paid', 'waived'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function charge()
    {
        return $this->belongsTo(FinancialCharge::class, 'financial_charge_id');
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function allocations()
    {
        return $this->hasMany(ArPaymentAllocation::class, 'account_receivable_id');
    }

    public function scopeOutstanding($q)
    {
        return $q->where('balance', '>', 0)->whereIn('status', ['open', 'partial']);
    }

    public function scopeOverdue($q)
    {
        return $q->where('balance', '>', 0)
            ->whereIn('status', ['open', 'partial'])
            ->where('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return (float) $this->balance > 0
            && in_array($this->status, ['open', 'partial'], true)
            && $this->due_date && $this->due_date->isPast();
    }

    /**
     * Mag-apply ng bayad sa isang AR row para sa isang payment.
     * Ibinabalik ang sukli (leftover). Dapat nasa loob ng DB transaction.
     */
    public function applyPayment(float $amount, ?int $paymentId = null): float
    {
        $amount = max(0, $amount);
        $apply = min($amount, (float) $this->balance);
        if ($apply > 0) {
            $paid = (float) $this->paid_amount + $apply;
            $balance = (float) $this->billed_amount - $paid;
            $this->update([
                'paid_amount' => $paid,
                'balance' => max(0, $balance),
                'status' => $balance <= 0 ? 'paid' : 'partial',
            ]);
            if ($paymentId) {
                ArPaymentAllocation::updateOrCreate(
                    ['account_receivable_id' => $this->id, 'payment_id' => $paymentId],
                    ['amount' => (float) (ArPaymentAllocation::where('account_receivable_id', $this->id)->where('payment_id', $paymentId)->value('amount') ?? 0) + $apply]
                );
            }
        }

        return $amount - $apply;
    }

    /**
     * FIFO: ibawas ang bayad sa pinakalumang open na AR ng estudyante.
     * Dapat nasa loob ng DB transaction.
     */
    public static function applyPaymentToStudent(int $studentId, float $amount, ?int $paymentId = null): float
    {
        $remaining = max(0, $amount);
        if ($remaining <= 0) return 0;
        if ($paymentId) {
            $already = (float) ArPaymentAllocation::where('payment_id', $paymentId)->sum('amount');
            $remaining = max(0, $remaining - $already);
            if ($remaining <= 0) return 0;
        }
        $rows = static::where('student_id', $studentId)
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($rows as $row) {
            if ($remaining <= 0) break;
            $remaining = $row->applyPayment($remaining, $paymentId);
        }

        return $remaining;
    }

    /**
     * Ibalik (reverse) ang lahat ng allocation ng isang payment sa AR.
     * Dapat nasa loob ng DB transaction.
     */
    public static function reversePaymentAllocations(int $paymentId): void
    {
        $allocs = ArPaymentAllocation::where('payment_id', $paymentId)->lockForUpdate()->get();
        foreach ($allocs as $a) {
            $row = static::lockForUpdate()->find($a->account_receivable_id);
            if ($row) {
                $paid = max(0, (float) $row->paid_amount - (float) $a->amount);
                $balance = (float) $row->billed_amount - $paid;
                $row->update([
                    'paid_amount' => $paid,
                    'balance' => max(0, $balance),
                    'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'open'),
                ]);
            }
            $a->delete();
        }
    }

    /**
     * Kung ang payment ay collected (approved/verified) o hindi.
     * Kaparehong depinisyon ng RevenueController::syncStudentAccount
     * para laging tugma ang AR sa student_accounts.total_paid.
     */
    public static function isCollected(Payment $payment): bool
    {
        return in_array($payment->status, ['approved', 'verified'], true)
            || in_array($payment->verification_status, ['verified', 'reconciled'], true);
    }

    /**
     * Single entry point ng LAHAT ng payment mutations:
     * approve, verify, reconcile, update ng amount, reject.
     * Idempotent — pwedeng tawagin nang paulit-ulit.
     * Dapat nasa loob ng DB transaction.
     */
    public static function resyncPayment(Payment $payment): void
    {
        static::reversePaymentAllocations($payment->id);
        $fresh = $payment->fresh();
        if ($fresh && static::isCollected($fresh)) {
            static::applyPaymentToStudent($fresh->student_id, (float) $fresh->amount, $fresh->id);
        }
    }

    public static function nextReferenceNumber(): string
    {
        return 'AR-'.now()->format('Ymd').'-'.strtoupper(uniqid());
    }
}
