<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\OnboardingSectionReview;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 4 — four-eyes approval: a reviewer hands an application off (maker),
 * a different manager/compliance officer decides (checker), and an approval
 * can't land until every section has been reviewed.
 */
class FourEyesApprovalTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;
    private UserOnboarding $onboarding;
    private QuestionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'client@test.com', 'name' => 'Acme Ltd', 'position' => 'CFO']);
        $this->onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(),
        ]);

        // One section with one answer, so there is something to review.
        $this->group = QuestionGroup::create(['name' => 'Company Details', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $this->group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $this->onboarding->id, 'value' => 'Acme Ltd']);
    }

    private function admin(AdminRole $role, string $email): Admin
    {
        return Admin::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'x', 'is_active' => true, 'role' => $role]);
    }

    private function markSectionReviewed(Admin $by): void
    {
        OnboardingSectionReview::create([
            'user_onboarding_id' => $this->onboarding->id, 'question_group_id' => $this->group->id,
            'status' => 'completed', 'reviewed_by' => $by->id, 'reviewed_at' => now(),
        ]);
    }

    // --- maker step ----------------------------------------------------------

    public function test_submit_for_approval_is_blocked_until_every_section_is_reviewed(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $this->onboarding))
            ->assertRedirect();

        $this->onboarding->refresh();
        $this->assertNull($this->onboarding->approval_state);
        $this->assertNull($this->onboarding->submitted_for_approval_by);
    }

    public function test_submit_for_approval_records_the_maker_once_sections_are_reviewed(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->markSectionReviewed($manager);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $this->onboarding))
            ->assertRedirect();

        $this->onboarding->refresh();
        $this->assertSame('pending_approval', $this->onboarding->approval_state);
        $this->assertSame($manager->id, $this->onboarding->submitted_for_approval_by);
        $this->assertDatabaseHas('onboarding_review_logs', [
            'user_onboarding_id' => $this->onboarding->id, 'event' => 'submitted_for_approval', 'admin_id' => $manager->id,
        ]);
    }

    public function test_an_analyst_can_submit_only_their_own_assigned_company(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $this->markSectionReviewed($analyst);

        // Not assigned → forbidden.
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $this->onboarding))
            ->assertForbidden();

        // Assigned → allowed.
        $this->onboarding->update(['assigned_to' => $analyst->id]);
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.submit-for-approval', $this->onboarding))
            ->assertRedirect();
        $this->assertSame('pending_approval', $this->onboarding->refresh()->approval_state);
    }

    // --- four-eyes checker rule ---------------------------------------------

    public function test_the_maker_cannot_approve_their_own_submission(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->markSectionReviewed($manager);
        $this->onboarding->update(['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $manager->id]);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.approve', $this->onboarding), ['comment' => 'lgtm'])
            ->assertRedirect();

        // Still completed — the self-approval was refused.
        $this->assertSame('completed', $this->onboarding->refresh()->status);
        $this->assertSame(session('error'), 'A different reviewer must decide this — you submitted it for approval (four-eyes).');
    }

    public function test_a_different_checker_can_approve_a_fully_reviewed_application(): void
    {
        $maker = $this->admin(AdminRole::Manager, 'maker@t.com');
        $checker = $this->admin(AdminRole::Compliance, 'checker@t.com');
        $this->markSectionReviewed($maker);
        $this->onboarding->update(['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $maker->id]);

        $this->actingAs($checker, 'admin')
            ->post(route('admin.user-onboardings.approve', $this->onboarding), ['comment' => 'approved'])
            ->assertRedirect();

        $this->onboarding->refresh();
        $this->assertSame('approved', $this->onboarding->status);
        $this->assertSame($checker->id, $this->onboarding->decided_by);
        $this->assertNull($this->onboarding->approval_state);
    }

    // --- section gate on approval -------------------------------------------

    public function test_approval_is_refused_when_sections_are_not_all_reviewed(): void
    {
        $checker = $this->admin(AdminRole::Manager, 'c@t.com'); // no section marked

        $this->actingAs($checker, 'admin')
            ->post(route('admin.user-onboardings.approve', $this->onboarding), ['comment' => 'x'])
            ->assertRedirect();

        $this->assertSame('completed', $this->onboarding->refresh()->status);
        $this->assertStringContainsString('Every section must be reviewed', session('error'));
    }

    public function test_rejection_does_not_require_all_sections_reviewed(): void
    {
        $checker = $this->admin(AdminRole::Manager, 'c@t.com');

        $this->actingAs($checker, 'admin')
            ->post(route('admin.user-onboardings.reject', $this->onboarding), ['comment' => 'Missing UBO evidence.'])
            ->assertRedirect();

        $this->assertSame('rejected', $this->onboarding->refresh()->status);
    }

    // --- escalation ----------------------------------------------------------

    public function test_escalating_flags_the_application_for_compliance(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.escalate', $this->onboarding), ['comment' => 'High-risk jurisdiction.'])
            ->assertRedirect();

        $this->onboarding->refresh();
        $this->assertTrue($this->onboarding->isEscalated());
        $this->assertDatabaseHas('onboarding_review_logs', [
            'user_onboarding_id' => $this->onboarding->id, 'event' => 'escalated',
        ]);
    }

    public function test_reopening_clears_the_approval_hand_off(): void
    {
        $maker = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->onboarding->update(['status' => 'rejected', 'approval_state' => 'escalated', 'submitted_for_approval_by' => $maker->id]);

        app(\App\Services\OnboardingService::class)->reopen($this->onboarding);

        $this->onboarding->refresh();
        $this->assertNull($this->onboarding->approval_state);
        $this->assertNull($this->onboarding->submitted_for_approval_by);
    }

    // --- queue filter --------------------------------------------------------

    public function test_queue_can_be_filtered_to_applications_awaiting_approval(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->onboarding->update(['approval_state' => 'pending_approval']);

        // A second application still in plain review.
        $other = UserOnboarding::create([
            'user_id' => User::create(['email' => 'o@t.com', 'name' => 'Other Co', 'position' => 'CEO'])->id,
            'user_type_id' => $this->type->id, 'status' => 'completed', 'started_at' => now(),
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.index', ['approval' => 'pending_approval']))
            ->assertOk()
            ->assertSee('Acme Ltd')
            ->assertDontSee('Other Co');
    }
}
