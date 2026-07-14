<?php

namespace App\Mail;

use App\Models\OnboardingMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nudge for a new thread message: to the admin team when a client writes,
 * to the client when the team replies.
 */
class NewMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        $onboarding = $this->message->onboarding;

        return new Envelope(
            subject: $this->message->sender_type === 'client'
                ? "New Client Message — {$onboarding->reference}"
                : "New Message from Eficyent — {$onboarding->reference}",
        );
    }

    public function content(): Content
    {
        $onboarding = $this->message->onboarding;
        $toAdmin = $this->message->sender_type === 'client';

        return new Content(
            view: 'emails.new-message',
            with: [
                // Not "message": Laravel injects the Symfony mail object
                // under that name into every mailable view.
                'threadMessage' => $this->message,
                'onboarding' => $onboarding,
                'toAdmin' => $toAdmin,
                'actionUrl' => $toAdmin
                    ? route('admin.user-onboardings.show', $onboarding)
                    : rtrim(config('app.frontend_url'), '/') . '/home?messages=1',
                'actionLabel' => $toAdmin ? 'View Application' : 'Read & Reply',
            ],
        );
    }
}
