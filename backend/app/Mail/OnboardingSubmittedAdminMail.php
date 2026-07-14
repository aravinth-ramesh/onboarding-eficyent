<?php

namespace App\Mail;

use App\Models\UserOnboarding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Heads-up to the admin team when a client submits their onboarding,
 * with a direct link to the review page.
 */
class OnboardingSubmittedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->onboarding->user?->name ?? $this->onboarding->user?->email ?? 'A client';

        return new Envelope(
            subject: "New Onboarding Submitted — {$client} ({$this->onboarding->reference})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-submitted-admin',
            with: [
                'onboarding' => $this->onboarding,
                'reviewUrl' => route('admin.user-onboardings.show', $this->onboarding),
            ],
        );
    }
}
