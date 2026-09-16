<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonorOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $donorName,
        public int $ttlMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your donation form verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.donor-otp',
        );
    }
}
