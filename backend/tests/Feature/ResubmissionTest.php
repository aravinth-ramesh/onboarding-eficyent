<?php

namespace Tests\Feature;

use App\Mail\OnboardingSubmittedAdminMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResubmissionTest extends TestCase
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
        OnboardingStep::create(['name' => 'Questions', 'slug' => 'questions', 'component_key' => 'questions', 'order' => 1, 'is_active' => true]);
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 2, 'is_active' => true]);
    }

    private function rejectedOnboarding()
    {
        $onboarding = $this->service->initializeForUser($this->user);
        foreach ($onboarding->steps()->orderBy('order')->get() as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }
        $this->service->reject($onboarding->fresh(), $this->admin, 'UBO documentation incomplete.');

        return $onboarding->fresh();
    }

    public function test_client_can_reopen_a_rejected_application(): void
    {
        $onboarding = $this->rejectedOnboarding();

        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/onboarding/reopen')->assertOk();

        $this->assertSame('in_progress', $response->json('data.status'));
        $this->assertSame('review', $response->json('data.current_step.component_key'));
        $this->assertNull($response->json('data.decision_comment'));

        $onboarding->refresh();
        $this->assertSame('in_progress', $onboarding->status);
        $this->assertNotNull($onboarding->reopened_at);
        $this->assertNull($onboarding->decided_at);
        $this->assertNull($onboarding->decision_comment);

        // Earlier steps stay completed so edit-at-review still works.
        $this->assertSame('completed', $onboarding->steps()->orderBy('order')->first()->status);

        // The submission lock is lifted again: the request gets past the 403
        // guard and into ordinary field validation.
        $this->postJson('/api/onboarding/registration', ['country_code' => 'GB'])
            ->assertStatus(422);
    }

    public function test_only_rejected_applications_can_be_reopened(): void
    {
        $onboarding = $this->rejectedOnboarding();
        $this->service->reopen($onboarding);

        Sanctum::actingAs($this->user);
        // Already reopened (in_progress) → cannot reopen again.
        $this->postJson('/api/onboarding/reopen')->assertStatus(403);

        // Approved applications cannot be reopened either.
        foreach ($onboarding->fresh()->steps()->where('status', '!=', 'completed')->orderBy('order')->get() as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }
        $this->service->approve($onboarding->fresh(), $this->admin);
        $this->postJson('/api/onboarding/reopen')->assertStatus(403);
    }

    public function test_resubmission_notifies_admins_with_marker(): void
    {
        $onboarding = $this->rejectedOnboarding();
        $this->service->reopen($onboarding);

        // Client fixes things and completes the review step again.
        $reviewStep = $onboarding->fresh()->steps()->where('component_key', 'review')->first();
        $this->service->completeStep($onboarding->fresh(), $reviewStep);

        $onboarding->refresh();
        $this->assertSame('completed', $onboarding->status);

        Mail::assertQueued(OnboardingSubmittedAdminMail::class, function ($mail) {
            return $mail->onboarding->reopened_at !== null
                && str_contains($mail->envelope()->subject, 'Resubmitted');
        });
    }
}
