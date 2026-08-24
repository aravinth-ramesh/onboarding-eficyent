<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Approve and reject are gated identically: an assigned application belongs to
 * its reviewer, so an uninvolved admin can't take a terminal, client-facing
 * decision on someone else's case. Rejection used to be reachable when
 * approval was not (bug report EOP-89).
 */
class DecisionAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
    }

    private function admin(AdminRole $role, string $email): Admin
    {
        return Admin::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'x', 'is_active' => true, 'role' => $role]);
    }

    private function onboarding(?Admin $assignee = null, array $attrs = []): UserOnboarding
    {
        $user = User::create(['email' => uniqid().'@t.com', 'name' => 'Co', 'position' => 'CFO']);

        return UserOnboarding::create(array_merge([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
            'assigned_to' => $assignee?->id,
        ], $attrs));
    }

    public function test_an_uninvolved_manager_cannot_reject_someone_elses_assigned_application(): void
    {
        $assignee = $this->admin(AdminRole::Analyst, 'assignee@t.com');
        $other = $this->admin(AdminRole::Manager, 'other@t.com');
        $onboarding = $this->onboarding($assignee);

        $this->expectException(\DomainException::class);
        app(OnboardingService::class)->reject($onboarding, $other, 'Not good enough.');
    }

    public function test_the_same_rule_blocks_approval_so_the_two_are_consistent(): void
    {
        $assignee = $this->admin(AdminRole::Analyst, 'assignee@t.com');
        $other = $this->admin(AdminRole::Manager, 'other@t.com');
        $onboarding = $this->onboarding($assignee);

        $this->expectException(\DomainException::class);
        app(OnboardingService::class)->approve($onboarding, $other);
    }

    public function test_the_assignee_can_still_decide(): void
    {
        $assignee = $this->admin(AdminRole::Manager, 'assignee@t.com');
        $onboarding = $this->onboarding($assignee);

        app(OnboardingService::class)->reject($onboarding, $assignee, 'Missing documents.');

        $this->assertSame('rejected', $onboarding->fresh()->status);
    }

    public function test_unassigned_work_is_open_to_any_eligible_admin(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $onboarding = $this->onboarding(null);

        app(OnboardingService::class)->reject($onboarding, $manager, 'Duplicate application.');

        $this->assertSame('rejected', $onboarding->fresh()->status);
    }

    public function test_four_eyes_hand_off_still_opens_the_decision_to_a_checker(): void
    {
        // The whole point of submitting for approval is to invite someone else
        // to decide — the assignment rule must not break that.
        $maker = $this->admin(AdminRole::Analyst, 'maker@t.com');
        $checker = $this->admin(AdminRole::Manager, 'checker@t.com');
        $onboarding = $this->onboarding($maker, [
            'approval_state' => 'pending_approval',
            'submitted_for_approval_by' => $maker->id,
            'submitted_for_approval_at' => now(),
        ]);

        app(OnboardingService::class)->approve($onboarding, $checker);

        $this->assertSame('approved', $onboarding->fresh()->status);
        $this->assertSame($checker->id, $onboarding->fresh()->decided_by);
    }

    public function test_an_escalated_application_is_open_to_compliance(): void
    {
        $assignee = $this->admin(AdminRole::Analyst, 'assignee@t.com');
        $compliance = $this->admin(AdminRole::Compliance, 'c@t.com');
        $onboarding = $this->onboarding($assignee, ['approval_state' => 'escalated']);

        app(OnboardingService::class)->reject($onboarding, $compliance, 'Sanctions concern.');

        $this->assertSame('rejected', $onboarding->fresh()->status);
    }

    public function test_an_admin_can_step_in_so_an_absent_reviewer_is_never_a_deadlock(): void
    {
        $assignee = $this->admin(AdminRole::Analyst, 'assignee@t.com');
        $superAdmin = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $onboarding = $this->onboarding($assignee);

        app(OnboardingService::class)->reject($onboarding, $superAdmin, 'Withdrawn by the client.');

        $this->assertSame('rejected', $onboarding->fresh()->status);
    }

    public function test_the_maker_still_cannot_sign_off_their_own_hand_off(): void
    {
        $maker = $this->admin(AdminRole::Manager, 'maker@t.com');
        $onboarding = $this->onboarding($maker, [
            'approval_state' => 'pending_approval',
            'submitted_for_approval_by' => $maker->id,
            'submitted_for_approval_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        app(OnboardingService::class)->approve($onboarding, $maker);
    }
}
