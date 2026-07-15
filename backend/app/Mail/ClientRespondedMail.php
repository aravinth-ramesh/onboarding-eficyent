<?php

namespace App\Mail;

use App\Models\AdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the review team a client has answered a change request or a new
 * question — closing the loop that previously went silent once the client
 * responded.
 */
class ClientRespondedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminNotification $adminNotification,
        public string $summary,
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->adminNotification->user?->name
            ?? $this->adminNotification->user?->email
            ?? 'A client';

        $what = $this->adminNotification->type === 'change_request'
            ? 'updated their answer'
            : 'answered your question';

        return new Envelope(subject: "{$client} {$what} — action complete");
    }

    public function content(): Content
    {
        $onboarding = $this->adminNotification->user?->activeOnboarding();

        return new Content(
            view: 'emails.client-responded',
            with: [
                'notification' => $this->adminNotification,
                'onboarding' => $onboarding,
                'summary' => $this->summary,
                'reviewUrl' => $onboarding
                    ? route('admin.user-onboardings.show', $onboarding)
                    : rtrim(config('app.url'), '/') . '/admin/user-onboardings',
            ],
        );
    }
}
