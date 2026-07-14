<?php

namespace Tests\Feature;

use App\Mail\OnboardingSubmittedAdminMail;
use App\Mail\OnboardingSubmittedClientMail;
use App\Models\Admin;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->service = app(OnboardingService::class);

        Admin::create(['name' => 'Reviewer One', 'email' => 'one@test.com', 'password' => 'x', 'is_active' => true]);
        Admin::create(['name' => 'Reviewer Two', 'email' => 'two@test.com', 'password' => 'x', 'is_active' => true]);
        Admin::create(['name' => 'Gone', 'email' => 'inactive@test.com', 'password' => 'x', 'is_active' => false]);

        // A deterministic two-step master flow (the data migrations leave a
        // variable number of steps on a fresh DB).
        \App\Models\OnboardingStep::query()->delete();
        \App\Models\OnboardingStep::create(['name' => 'Select Type', 'slug' => 'select-type', 'component_key' => 'select_type', 'order' => 1, 'is_active' => true]);
        \App\Models\OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 2, 'is_active' => true]);
    }

    /** Complete every step of a fresh onboarding and return it. */
    private function submitOnboarding()
    {
        $onboarding = $this->service->initializeForUser($this->user);
        foreach ($onboarding->steps()->orderBy('order')->get() as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_client_gets_confirmation_and_active_admins_are_notified(): void
    {
        $onboarding = $this->submitOnboarding();
        $this->assertSame('completed', $onboarding->status);

        Mail::assertQueued(OnboardingSubmittedClientMail::class, function ($mail) use ($onboarding) {
            return $mail->hasTo('client@test.com')
                && $mail->onboarding->is($onboarding);
        });

        Mail::assertQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('one@test.com'));
        Mail::assertQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('two@test.com'));
        Mail::assertNotQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('inactive@test.com'));
    }

    public function test_no_submission_mail_before_the_final_step(): void
    {
        $onboarding = $this->service->initializeForUser($this->user);
        $firstStep = $onboarding->steps()->orderBy('order')->first();

        $this->service->completeStep($onboarding, $firstStep);

        Mail::assertNothingQueued();
    }

    public function test_emails_render_reference_and_links(): void
    {
        config(['app.frontend_url' => 'https://portal.example.com']);
        $onboarding = $this->submitOnboarding();

        $clientHtml = (new OnboardingSubmittedClientMail($onboarding))->render();
        $this->assertStringContainsString($onboarding->reference, $clientHtml);
        $this->assertStringContainsString('https://portal.example.com/home', $clientHtml);

        $adminHtml = (new OnboardingSubmittedAdminMail($onboarding))->render();
        $this->assertStringContainsString($onboarding->reference, $adminHtml);
        $this->assertStringContainsString('client@test.com', $adminHtml);
        $this->assertStringContainsString(route('admin.user-onboardings.show', $onboarding), $adminHtml);
    }

    public function test_reference_format_matches_portal(): void
    {
        $onboarding = $this->submitOnboarding();

        $this->assertSame(
            'ONB-' . $onboarding->started_at->format('Y') . '-' . str_pad((string) $onboarding->id, 4, '0', STR_PAD_LEFT),
            $onboarding->reference,
        );
    }
}
