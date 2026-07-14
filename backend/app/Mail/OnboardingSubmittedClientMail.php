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
 * Confirmation sent to the client the moment their onboarding is submitted.
 */
class OnboardingSubmittedClientMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Onboarding Submitted — Reference {$this->onboarding->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-submitted-client',
            with: [
                'onboarding' => $this->onboarding,
                'portalUrl' => rtrim(config('app.frontend_url'), '/') . '/home',
            ],
        );
    }
}
