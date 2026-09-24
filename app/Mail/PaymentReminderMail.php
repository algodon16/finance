<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $paymentType,
        public string $amountDue,
        public string $deadline
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Reminder – Upcoming Payment Deadline'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder'
        );
    }
}
