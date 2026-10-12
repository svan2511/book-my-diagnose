<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $expiresMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Book My Diagnose admin sign-in code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-otp',
            with: [
                'name' => $this->user->name,
                'code' => $this->code,
                'minutes' => $this->expiresMinutes,
            ],
        );
    }
}
