<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\OnboardingSubmittedAdminMail;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Submission notifications go to the assigned reviewer (or managers when
 * unassigned) — not every admin (bug report EOP-87).
 */
class SubmissionNotificationScopeTest extends TestCase
{
    use RefreshDatabase;

    private function notify(UserOnboarding $onboarding): void
    {
        $service = app(OnboardingService::class);
        $m = new \ReflectionMethod($service, 'notifySubmission');
        $m->setAccessible(true);
        $m->invoke($service, $onboarding);
    }

    public function test_only_the_assignee_is_emailed_when_assigned(): void
    {
        Mail::fake();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $assignee = Admin::create(['name' => 'A', 'email' => 'assignee@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);
        Admin::create(['name' => 'Other', 'email' => 'other@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'assigned_to' => $assignee->id]);

        $this->notify($onb);

        Mail::assertQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('assignee@t.com'));
        Mail::assertNotQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('other@t.com'));
    }

    public function test_unassigned_submissions_go_to_managers_not_analysts(): void
    {
        Mail::fake();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $manager = Admin::create(['name' => 'M', 'email' => 'mgr@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);
        $analyst = Admin::create(['name' => 'An', 'email' => 'analyst@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);

        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now()]);

        $this->notify($onb);

        Mail::assertQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('mgr@t.com'));
        Mail::assertNotQueued(OnboardingSubmittedAdminMail::class, fn ($m) => $m->hasTo('analyst@t.com'));
    }
}
