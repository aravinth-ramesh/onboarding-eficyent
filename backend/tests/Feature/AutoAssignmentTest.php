<?php

namespace Tests\Feature;

use App\Mail\OnboardingAssignedMail;
use App\Models\Admin;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $alice;
    private Admin $bob;
    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['onboarding.auto_assign_submissions' => true]);

        $this->alice = Admin::create(['name' => 'Alice Admin', 'email' => 'alice@test.com', 'password' => 'x', 'is_active' => true]);
        $this->bob = Admin::create(['name' => 'Bob Admin', 'email' => 'bob@test.com', 'password' => 'x', 'is_active' => true]);
        Admin::create(['name' => 'Inactive', 'email' => 'gone@test.com', 'password' => 'x', 'is_active' => false]);

        $this->service = app(OnboardingService::class);

        OnboardingStep::query()->delete();
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 1, 'is_active' => true]);
    }

    private function submit(string $email): UserOnboarding
    {
        $user = User::create(['email' => $email, 'name' => 'Client ' . $email, 'position' => 'CFO']);
        $onboarding = $this->service->initializeForUser($user);
        foreach ($onboarding->steps as $step) {
            $this->service->completeStep($onboarding->fresh(), $step);
        }

        return $onboarding->fresh();
    }

    public function test_submission_is_assigned_to_the_least_loaded_active_admin(): void
    {
        // Alice already carries an open review; Bob is free.
        $type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $busy = User::create(['email' => 'busy@test.com', 'name' => 'Busy', 'position' => 'CFO']);
        UserOnboarding::create([
            'user_id' => $busy->id, 'user_type_id' => $type->id,
            'status' => 'completed', 'assigned_to' => $this->alice->id, 'started_at' => now(),
        ]);

        $onboarding = $this->submit('a@test.com');

        $this->assertSame($this->bob->id, $onboarding->assigned_to);
        Mail::assertQueued(OnboardingAssignedMail::class, function ($m) {
            return $m->hasTo('bob@test.com') && $m->assignedBy === null;
        });
    }

    public function test_load_balances_across_successive_submissions(): void
    {
        $first = $this->submit('one@test.com');
        $second = $this->submit('two@test.com');

        // One each — not both on the same admin.
        $this->assertNotSame($first->assigned_to, $second->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$this->alice->id, $this->bob->id],
            [$first->assigned_to, $second->assigned_to],
        );
    }

    public function test_resubmission_keeps_the_existing_reviewer(): void
    {
        $onboarding = $this->submit('a@test.com');
        $onboarding->update(['assigned_to' => $this->alice->id]);

        $this->service->reject($onboarding->fresh(), $this->bob, 'Fix the UBO section.');
        $this->service->reopen($onboarding->fresh());
        $reviewStep = $onboarding->fresh()->steps()->where('component_key', 'review')->first();
        $this->service->completeStep($onboarding->fresh(), $reviewStep);

        $this->assertSame($this->alice->id, $onboarding->fresh()->assigned_to);
    }

    public function test_disabled_config_leaves_submissions_unassigned(): void
    {
        config(['onboarding.auto_assign_submissions' => false]);

        $onboarding = $this->submit('a@test.com');

        $this->assertNull($onboarding->assigned_to);
        Mail::assertNotQueued(OnboardingAssignedMail::class);
    }

    public function test_no_active_admins_leaves_submission_unassigned(): void
    {
        Admin::query()->update(['is_active' => false]);

        $onboarding = $this->submit('a@test.com');

        $this->assertNull($onboarding->assigned_to);
    }

    public function test_auto_assignment_email_mentions_automation(): void
    {
        $onboarding = $this->submit('a@test.com');

        $html = (new OnboardingAssignedMail($onboarding->load(['user', 'userType']), null))->render();
        $this->assertStringContainsString('automatically assigned to you', $html);
        $this->assertStringContainsString($onboarding->reference, $html);
    }
}
