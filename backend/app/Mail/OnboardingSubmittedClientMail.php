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
        return new Envelope(subject: $this->template()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding-submitted-client',
            with: [
                'onboarding' => $this->onboarding,
                'bodyText' => $this->template()['body'],
                'portalUrl' => rtrim(config('app.frontend_url'), '/') . '/home',
            ],
        );
    }

    /** @return array{subject: string, body: string} */
    private function template(): array
    {
        return app(\App\Services\EmailTemplateService::class)->render('onboarding_submitted_client', [
            'client_name' => $this->onboarding->user->name ?? 'there',
            'reference' => $this->onboarding->reference,
            'organization_type' => $this->onboarding->userType->name ?? '',
        ]);
    }
}
