<?php

namespace Tests\Feature;

use App\Mail\OnboardingDecisionMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Admin $admin;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->user = User::create(['email' => 'client@test.com', 'name' => 'Test Client', 'position' => 'CFO']);
        $this->admin = Admin::create(['name' => 'Reviewer', 'email' => 'admin@test.com', 'password' => 'x', 'is_active' => true]);
        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function submittedOnboarding()
    {
        $onboarding = $this->service->initializeForUser($this->user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_admin_can_approve_a_submitted_application(): void
    {
        $onboarding = $this->submittedOnboarding();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.approve', $onboarding), ['comment' => 'All checks passed.'])
            ->assertRedirect(route('admin.user-onboardings.show', $onboarding));

        $onboarding->refresh();
        $this->assertSame('approved', $onboarding->status);
        $this->assertSame($this->admin->id, $onboarding->decided_by);
        $this->assertNotNull($onboarding->decided_at);
        $this->assertSame('All checks passed.', $onboarding->decision_comment);

        Mail::assertQueued(OnboardingDecisionMail::class, fn ($m) => $m->hasTo('client@test.com'));
    }

    public function test_rejection_requires_a_reason(): void
    {
        $onboarding = $this->submittedOnboarding();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.reject', $onboarding), [])
            ->assertSessionHasErrors('comment');

        $this->assertSame('completed', $onboarding->fresh()->status);
        Mail::assertNotQueued(OnboardingDecisionMail::class);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.reject', $onboarding), ['comment' => 'UBO documentation incomplete.'])
            ->assertRedirect();

        $onboarding->refresh();
        $this->assertSame('rejected', $onboarding->status);
        $this->assertSame('UBO documentation incomplete.', $onboarding->decision_comment);
        Mail::assertQueued(OnboardingDecisionMail::class);
    }

    public function test_only_submitted_applications_can_be_decided(): void
    {
        $onboarding = $this->service->initializeForUser($this->user); // still in_progress

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.approve', $onboarding), [])
            ->assertSessionHas('error');

        $this->assertSame('pending', $onboarding->fresh()->status);

        // And a decided application cannot be decided again.
        $this->user = User::create(['email' => 'client2@test.com', 'name' => 'Second Client', 'position' => 'CEO']);
        $submitted = $this->submittedOnboarding();
        $this->service->approve($submitted, $this->admin);
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.reject', $submitted->fresh(), ), ['comment' => 'changed my mind'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $submitted->fresh()->status);
    }

    public function test_client_status_endpoint_exposes_the_decision(): void
    {
        $onboarding = $this->submittedOnboarding();
        $this->service->reject($onboarding, $this->admin, 'Registration number could not be verified.');

        Sanctum::actingAs($this->user);
        $this->get('/api/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_comment', 'Registration number could not be verified.');
    }

    public function test_decided_applications_stay_locked_for_edits(): void
    {
        $onboarding = $this->submittedOnboarding();
        $this->service->approve($onboarding, $this->admin);

        Sanctum::actingAs($this->user);
        $this->postJson('/api/onboarding/registration', ['country_code' => 'GB'])
            ->assertStatus(403);
    }

    public function test_decision_email_contains_reason_and_reference(): void
    {
        $onboarding = $this->submittedOnboarding();
        $this->service->reject($onboarding, $this->admin, 'Missing UBO declarations.');

        $html = (new OnboardingDecisionMail($onboarding->fresh()))->render();

        $this->assertStringContainsString('was not approved', $html);
        $this->assertStringContainsString('Missing UBO declarations.', $html);
        $this->assertStringContainsString($onboarding->reference, $html);
    }
}
