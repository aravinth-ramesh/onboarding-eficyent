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
        return new Envelope(subject: $this->template()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-decision',
            with: [
                'onboarding' => $this->onboarding,
                'approved' => $this->onboarding->status === 'approved',
                'bodyText' => $this->template()['body'],
                'portalUrl' => rtrim(config('app.frontend_url'), '/') . '/home',
            ],
        );
    }

    /** @return array{subject: string, body: string} */
    private function template(): array
    {
        $key = $this->onboarding->status === 'approved' ? 'onboarding_approved' : 'onboarding_rejected';

        return app(\App\Services\EmailTemplateService::class)->render($key, [
            'client_name' => $this->onboarding->user->name ?? 'there',
            'reference' => $this->onboarding->reference,
        ]);
    }
}
