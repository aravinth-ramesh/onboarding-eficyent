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
 * Tells the compliance team an application has been escalated to them for a
 * decision, with the escalating reviewer's note.
 */
class OnboardingEscalatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserOnboarding $onboarding,
        public ?Admin $escalatedBy = null,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Escalated to Compliance — {$this->onboarding->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-escalated',
            with: [
                'onboarding' => $this->onboarding,
                'escalatedBy' => $this->escalatedBy,
                'note' => $this->note,
                'reviewUrl' => route('admin.user-onboardings.show', $this->onboarding),
            ],
        );
    }
}
