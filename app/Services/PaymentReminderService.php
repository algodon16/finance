<?php

namespace App\Services;

use App\Mail\PaymentReminderMail;
use App\Models\FinancialCharge;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\StudentAccount;
use App\Notifications\Channels\EmailChannel;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Throwable;

class PaymentReminderService
{
    public const TYPE = 'payment_reminder';

    /**
     * Reminder windows in days before the deadline.
     * Key = reminder_type stored in notification_logs.
     */
    public const WINDOWS = [
        '7_days' => 7,
        '3_days' => 3,
        '1_day' => 1,
        'due_today' => 0,
    ];

    protected EmailChannel $channel;

    public function __construct(?EmailChannel $channel = null)
    {
        $this->channel = $channel ?? new EmailChannel();
    }

    /**
     * Scan charges with approaching/past deadlines and send reminders.
     * Safe to run repeatedly — already-sent reminders are never resent.
     */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $stats = ['checked' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'disabled' => false];

        if (! NotificationSetting::enabled('payment_reminders.enabled')) {
            $stats['disabled'] = true;

            return $stats;
        }

        foreach (self::WINDOWS as $reminderType => $daysBefore) {
            if (! NotificationSetting::enabled("payment_reminders.{$reminderType}")) {
                continue;
            }

            $charges = $this->dueCharges($today->copy()->addDays($daysBefore));

            foreach ($charges as $charge) {
                $stats['checked']++;
                $this->process($charge, $reminderType, $today, $stats);
            }
        }

        if (NotificationSetting::enabled('payment_reminders.overdue')) {
            $charges = $this->overdueCharges($today);

            foreach ($charges as $charge) {
                $stats['checked']++;
                $this->process($charge, 'overdue', $today, $stats);
            }
        }

        return $stats;
    }

    protected function dueCharges(Carbon $date)
    {
        return FinancialCharge::with(['student.user', 'student.studentAccount', 'financialCategory'])
            ->whereDate('due_date', $date->toDateString())
            ->whereIn('status', ['active', 'overdue'])
            ->get();
    }

    protected function overdueCharges(Carbon $today)
    {
        return FinancialCharge::with(['student.user', 'student.studentAccount', 'financialCategory'])
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereIn('status', ['active', 'overdue'])
            ->get();
    }

    protected function process(FinancialCharge $charge, string $reminderType, Carbon $today, array &$stats): void
    {
        $student = $charge->student;

        // Already paid / waived / no balance → stop, never remind.
        if (! $student || ! $student->studentAccount || (float) $student->studentAccount->outstanding_balance <= 0) {
            $stats['skipped']++;

            return;
        }

        // Duplicate protection: a sent reminder is never sent again.
        $existing = NotificationLog::where('student_id', $student->id)
            ->where('financial_charge_id', $charge->id)
            ->where('reminder_type', $reminderType)
            ->first();

        if ($existing && $existing->delivery_status === 'sent') {
            $stats['skipped']++;

            return;
        }

        $email = $student->user->email ?? null;
        $deadline = $charge->due_date ? Carbon::parse($charge->due_date) : null;

        if (! $deadline || ! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->recordFailure(
                $student->id,
                $charge,
                $reminderType,
                $email,
                $deadline,
                $deadline ? 'Missing or invalid student email address.' : 'Missing payment deadline.'
            );
            $stats['failed']++;

            return;
        }

        $paymentType = $charge->financialCategory->name ?? $charge->description;
        $amountDue = number_format((float) $student->studentAccount->outstanding_balance, 2);
        $deadlineLabel = $deadline->format('F d, Y');

        try {
            $this->channel->send($email, new PaymentReminderMail(
                $student->full_name,
                $paymentType,
                $amountDue,
                $deadlineLabel
            ));

            $this->recordSent($student->id, $charge, $reminderType, $email, (float) $student->studentAccount->outstanding_balance, $deadline);
            $stats['sent']++;

            // Student header/bell notification (unread initially).
            NotificationService::create(
                $student->user_id,
                'Payment Reminder',
                $this->bellMessage($reminderType, $amountDue, $deadlineLabel)
            );
        } catch (Throwable $e) {
            $this->recordFailure(
                $student->id,
                $charge,
                $reminderType,
                $email,
                $deadline,
                'Email delivery failed: ' . $e->getMessage()
            );
            $stats['failed']++;
        }
    }

    protected function recordSent(int $studentId, FinancialCharge $charge, string $reminderType, string $email, float $amountDue, Carbon $deadline): void
    {
        try {
            NotificationLog::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'financial_charge_id' => $charge->id,
                    'reminder_type' => $reminderType,
                ],
                [
                    'notification_type' => self::TYPE,
                    'recipient_email' => $email,
                    'subject' => 'Payment Reminder – Upcoming Payment Deadline',
                    'amount_due' => $amountDue,
                    'deadline' => $deadline->toDateString(),
                    'sent_at' => now(),
                    'delivery_status' => 'sent',
                    'error_message' => null,
                ]
            );
        } catch (QueryException $e) {
            // Unique-violation race (overlapping scheduler runs) → already recorded.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }
    }

    protected function recordFailure(int $studentId, FinancialCharge $charge, string $reminderType, ?string $email, ?Carbon $deadline, string $reason): void
    {
        $account = StudentAccount::where('student_id', $studentId)->first();

        try {
            NotificationLog::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'financial_charge_id' => $charge->id,
                    'reminder_type' => $reminderType,
                ],
                [
                    'notification_type' => self::TYPE,
                    'recipient_email' => $email,
                    'subject' => 'Payment Reminder – Upcoming Payment Deadline',
                    'amount_due' => $account ? (float) $account->outstanding_balance : null,
                    'deadline' => $deadline?->toDateString(),
                    'delivery_status' => 'failed',
                    'error_message' => $reason,
                ]
            );
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }

    protected function bellMessage(string $reminderType, string $amountDue, string $deadlineLabel): string
    {
        return match ($reminderType) {
            '7_days' => "Your payment of ₱{$amountDue} is due in 7 days. Deadline: {$deadlineLabel}",
            '3_days' => "Your payment of ₱{$amountDue} is due in 3 days. Deadline: {$deadlineLabel}",
            '1_day' => "Your payment of ₱{$amountDue} is due tomorrow. Deadline: {$deadlineLabel}",
            'due_today' => "Your payment of ₱{$amountDue} is due today. Deadline: {$deadlineLabel}",
            'overdue' => "Your payment of ₱{$amountDue} is overdue since {$deadlineLabel}. Please settle it as soon as possible.",
            default => "Your payment of ₱{$amountDue} has Deadline: {$deadlineLabel}",
        };
    }
}
