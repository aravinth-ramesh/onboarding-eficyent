<?php

namespace App\Services;

use App\Models\EmailTemplate;

/**
 * Admin-configurable wording for client-facing emails.
 *
 * Each template key has a code default (subject + body with {{placeholder}}
 * tokens) that admins can override from the panel. Rendering substitutes the
 * placeholders — plain text substitution only, nothing is evaluated. The
 * branded HTML shell (header, buttons, reference cards) stays in the Blade
 * views; templates control the words.
 */
class EmailTemplateService
{
    public const REGISTRY = [
        'onboarding_submitted_client' => [
            'label' => 'Submission Confirmation (to client)',
            'description' => 'Sent to the client the moment their application is submitted. The reference card and portal button are added automatically.',
            'placeholders' => [
                'client_name' => "The client's name",
                'reference' => 'Application reference (ONB-...)',
                'organization_type' => 'Selected organization type',
            ],
            'subject' => 'Onboarding Submitted — Reference {{reference}}',
            'body' => "Hello {{client_name}},\n\nThank you — we have received your onboarding application and our team will now review it. We will contact you by email if anything further is needed.\n\nPlease quote your reference {{reference}} in any correspondence with us.",
        ],
        'onboarding_approved' => [
            'label' => 'Application Approved (to client)',
            'description' => 'Sent when an admin approves the application. Any reviewer note is shown in a highlighted block below this text.',
            'placeholders' => [
                'client_name' => "The client's name",
                'reference' => 'Application reference',
            ],
            'subject' => 'Your Onboarding Has Been Approved — {{reference}}',
            'body' => "Hello {{client_name}},\n\nGood news — your onboarding application (reference {{reference}}) has been reviewed and approved. Welcome aboard! Our team will be in touch with the next steps.",
        ],
        'onboarding_rejected' => [
            'label' => 'Application Not Approved (to client)',
            'description' => 'Sent when an admin rejects the application. The mandatory rejection reason is shown in a highlighted block below this text.',
            'placeholders' => [
                'client_name' => "The client's name",
                'reference' => 'Application reference',
            ],
            'subject' => 'Update on Your Onboarding Application — {{reference}}',
            'body' => "Hello {{client_name}},\n\nWe have completed the review of your onboarding application (reference {{reference}}) and unfortunately it was not approved at this time.\n\nIf you believe this is an error or your circumstances change, please contact our team at support@eficyent.com, quoting your reference number.",
        ],
        'change_request' => [
            'label' => 'Change Request (to client)',
            'description' => 'Sent when an admin requests a change to one of the client\'s answers. A "View Review" button deep-links to the request.',
            'placeholders' => [
                'client_name' => "The client's name",
                'question_label' => 'The question the change concerns',
            ],
            'subject' => 'Action Required: Please Update Your Response - {{question_label}}',
            'body' => "Hello {{client_name}},\n\nWe have reviewed your onboarding submission and require some changes to one of your answers.\n\nQuestion: {{question_label}}\n\nPlease log in to your account to review the details and submit your updated response.\n\nThank you,\nEficyent Team",
        ],
        'new_question' => [
            'label' => 'New Question Assigned (to client)',
            'description' => 'Sent when an admin sends the client an additional question. A "View Review" button deep-links to it.',
            'placeholders' => [
                'client_name' => "The client's name",
                'question_label' => 'The new question',
            ],
            'subject' => 'New Question Assigned to You - {{question_label}}',
            'body' => "Hello {{client_name}},\n\nA new question has been assigned to you that requires your response.\n\nQuestion: {{question_label}}\n\nPlease log in to your account to provide your answer.\n\nThank you,\nEficyent Team",
        ],
    ];

    /**
     * @return array{subject: string, body: string}
     */
    public function render(string $key, array $vars = []): array
    {
        $definition = self::REGISTRY[$key] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown email template [{$key}].");
        }

        $override = EmailTemplate::where('key', $key)->first();

        return [
            'subject' => $this->substitute($override->subject ?? $definition['subject'], $vars),
            'body' => $this->substitute($override->body ?? $definition['body'], $vars),
        ];
    }

    /** Plain token replacement — {{name}} with optional inner whitespace. */
    private function substitute(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, $text);
    }
}
