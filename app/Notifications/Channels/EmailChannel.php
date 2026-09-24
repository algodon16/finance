<?php

namespace App\Notifications\Channels;

use App\Mail\PaymentReminderMail;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements ChannelInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function send(string $recipient, PaymentReminderMail $mail): void
    {
        Mail::to($recipient)->send($mail);
    }
}
