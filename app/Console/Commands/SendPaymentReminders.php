<?php

namespace App\Console\Commands;

use App\Services\PaymentReminderService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'reminders:send-payment';

    protected $description = 'Detect upcoming payment deadlines and send automatic payment reminders.';

    public function handle(PaymentReminderService $service): int
    {
        $stats = $service->run();

        if (! empty($stats['disabled'])) {
            $this->info('Automatic payment reminders are disabled.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Payment reminders checked: %d, sent: %d, skipped: %d, failed: %d.',
            $stats['checked'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
