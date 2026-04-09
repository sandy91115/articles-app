<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $userName,
        public string $code,
        public int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your Content Monetization account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-otp',
        );
    }
}
