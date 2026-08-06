<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserOnboardingStep;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A submitted or decided application is locked (bug report EOP-44, EOP-77,
 * EOP-103): the client can't re-open steps to edit, and an admin can't request
 * changes or add questions once it's approved.
 */
class PostApprovalLockTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
    }

    private function onboarding(string $status): UserOnboarding
    {
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $this->type->id, 'status' => $status, 'started_at' => now(), 'completed_at' => now()]);
        $m1 = \App\Models\OnboardingStep::create(['name' => 'One', 'slug' => 'one', 'component_key' => 'questions', 'order' => 1, 'is_active' => true]);
        $m2 = \App\Models\OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'component_key' => 'review', 'order' => 2, 'is_active' => true]);
        // Two steps, the last one current + completed.
        $s1 = UserOnboardingStep::create(['user_onboarding_id' => $onb->id, 'onboarding_step_id' => $m1->id, 'name' => 'One', 'component_key' => 'questions', 'order' => 1, 'status' => 'completed']);
        $s2 = UserOnboardingStep::create(['user_onboarding_id' => $onb->id, 'onboarding_step_id' => $m2->id, 'name' => 'Review', 'component_key' => 'review', 'order' => 2, 'status' => 'completed']);
        $onb->update(['current_step_id' => $s2->id]);

        return $onb->fresh();
    }

    public function test_going_to_a_step_does_not_reopen_a_submitted_application(): void
    {
        $onb = $this->onboarding('completed');
        $firstStep = $onb->steps()->orderBy('order')->first();

        app(OnboardingService::class)->goToStep($onb, $firstStep);

        $onb->refresh();
        $this->assertSame('completed', $onb->status, 'status must not revert to in_progress');
        $this->assertSame('completed', $firstStep->refresh()->status, 'the step must stay completed (not reopened)');
    }

    public function test_going_to_a_step_does_not_reopen_an_approved_application(): void
    {
        $onb = $this->onboarding('approved');
        $firstStep = $onb->steps()->orderBy('order')->first();

        app(OnboardingService::class)->goToStep($onb, $firstStep);

        $this->assertSame('approved', $onb->refresh()->status);
    }

    public function test_admin_cannot_request_changes_on_an_approved_application(): void
    {
        $onb = $this->onboarding('approved');
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $onb->user_id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => 'Acme']);

        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.user-onboardings.answers.request-change', [$onb, $answer]), ['message' => 'fix this'])
            ->assertRedirect();

        $this->assertSame(0, AdminNotification::where('type', 'change_request')->count(), 'no change request on an approved application');
    }

    public function test_admin_cannot_add_a_question_to_an_approved_application(): void
    {
        $onb = $this->onboarding('approved');
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.user-onboardings.new-question', $onb))
            ->assertRedirect(route('admin.user-onboardings.show', $onb));
    }
}
