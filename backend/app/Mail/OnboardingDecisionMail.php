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
 * Sent to the client when an admin approves or rejects their submitted
 * onboarding. The decision comment (mandatory on rejection) is included.
 */
class OnboardingDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->onboarding->status === 'approved'
            ? "Your Onboarding Has Been Approved — {$this->onboarding->reference}"
            : "Update on Your Onboarding Application — {$this->onboarding->reference}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-decision',
            with: [
                'onboarding' => $this->onboarding,
                'approved' => $this->onboarding->status === 'approved',
                'portalUrl' => rtrim(config('app.frontend_url'), '/') . '/home',
            ],
        );
    }
}
