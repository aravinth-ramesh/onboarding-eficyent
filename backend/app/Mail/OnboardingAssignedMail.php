<?php

namespace App\Mail;

use App\Models\Admin;
use App\Models\UserOnboarding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an admin an application has been assigned to them for review.
 */
class OnboardingAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
        public Admin $assignedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Application Assigned to You — {$this->onboarding->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-assigned',
            with: [
                'onboarding' => $this->onboarding,
                'assignedBy' => $this->assignedBy,
                'reviewUrl' => route('admin.user-onboardings.show', $this->onboarding),
            ],
        );
    }
}
