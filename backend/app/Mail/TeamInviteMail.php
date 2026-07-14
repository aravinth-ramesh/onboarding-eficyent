<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation to collaborate on an onboarding application.
 */
class TeamInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
        public User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        $who = $this->inviter->name ?? $this->inviter->email;

        return new Envelope(
            subject: "{$who} invited you to collaborate on their Eficyent onboarding",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invite',
            with: [
                'onboarding' => $this->onboarding,
                'inviter' => $this->inviter,
                'portalUrl' => rtrim(config('app.frontend_url'), '/') . '/home',
            ],
        );
    }
}
