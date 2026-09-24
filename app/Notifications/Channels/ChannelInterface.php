<?php

namespace App\Notifications\Channels;

use App\Mail\PaymentReminderMail;

/**
 * Delivery channel contract.
 *
 * To add a new channel later (e.g. SMS, Messenger), create a class
 * implementing this interface and register it in the dispatcher —
 * no changes to the reminder detection logic are required.
 */
interface ChannelInterface
{
    public function name(): string;

    public function send(string $recipient, PaymentReminderMail $mail): void;
}
