<?php

namespace Tests\Feature;

use App\Mail\OnboardingDecisionMail;
use App\Mail\OnboardingSubmittedClientMail;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function submittedOnboarding()
    {
        $user = User::create(['email' => 'client@test.com', 'name' => 'Jane Doe', 'position' => 'CFO']);
        $onboarding = $this->service->initializeForUser($user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh(['user', 'userType']);
    }

    public function test_defaults_render_with_placeholders_substituted(): void
    {
        $onboarding = $this->submittedOnboarding();

        $mail = new OnboardingSubmittedClientMail($onboarding);
        $this->assertSame("Onboarding Submitted — Reference {$onboarding->reference}", $mail->envelope()->subject);
        $this->assertStringContainsString('Hello Jane Doe', $mail->render());
    }

    public function test_customized_template_changes_the_outgoing_email(): void
    {
        $onboarding = $this->submittedOnboarding();

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.email-templates.update', 'onboarding_submitted_client'), [
                'subject' => 'We got it, {{client_name}}! ({{reference}})',
                'body' => "Thanks {{client_name}} — application {{reference}} for a {{organization_type}} is in review.",
            ])
            ->assertRedirect();

        $mail = new OnboardingSubmittedClientMail($onboarding->fresh(['user', 'userType']));
        $this->assertSame("We got it, Jane Doe! ({$onboarding->reference})", $mail->envelope()->subject);
        $this->assertStringContainsString('is in review', $mail->render());
    }

    public function test_decision_emails_use_their_templates(): void
    {
        EmailTemplate::create([
            'key' => 'onboarding_rejected',
            'subject' => 'Custom rejection — {{reference}}',
            'body' => 'Sorry {{client_name}}, see the note below.',
        ]);

        $onboarding = $this->submittedOnboarding();
        $this->service->reject($onboarding, $this->admin, 'Missing documents.');

        $mail = new OnboardingDecisionMail($onboarding->fresh(['user']));
        $this->assertStringContainsString('Custom rejection', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('see the note below', $html);
        // The structural reviewer-note block still renders the real comment.
        $this->assertStringContainsString('Missing documents.', $html);
    }

    public function test_change_request_defaults_come_from_the_registry(): void
    {
        $service = app(\App\Services\AdminEmailService::class);

        $subject = $service->getDefaultSubject('change_request', 'VAT Number');
        $body = $service->getDefaultBody('change_request', ['user_name' => 'Jane', 'question_label' => 'VAT Number']);

        $this->assertSame('Action Required: Please Update Your Response - VAT Number', $subject);
        $this->assertStringContainsString('Hello Jane', $body);
        $this->assertStringContainsString('Question: VAT Number', $body);
    }

    public function test_reset_returns_to_defaults_and_unknown_placeholders_are_left_intact(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.email-templates.update', 'onboarding_approved'), [
                'subject' => 'Approved {{reference}} {{not_a_real_placeholder}}',
                'body' => 'Done.',
            ]);

        $rendered = app(EmailTemplateService::class)->render('onboarding_approved', ['reference' => 'ONB-1']);
        $this->assertSame('Approved ONB-1 {{not_a_real_placeholder}}', $rendered['subject']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.email-templates.reset', 'onboarding_approved'))
            ->assertRedirect();

        $rendered = app(EmailTemplateService::class)->render('onboarding_approved', ['reference' => 'ONB-1']);
        $this->assertSame('Your Onboarding Has Been Approved — ONB-1', $rendered['subject']);
    }

    public function test_template_pages_render_and_reject_unknown_keys(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.index'))
            ->assertOk()
            ->assertSee('Submission Confirmation (to client)')
            ->assertSee('Default');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.edit', 'onboarding_submitted_client'))
            ->assertOk()
            ->assertSee('{{client_name}}', false)
            ->assertSee('ONB-2026-0042'); // sample preview

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.edit', 'nope'))
            ->assertNotFound();
    }
}
