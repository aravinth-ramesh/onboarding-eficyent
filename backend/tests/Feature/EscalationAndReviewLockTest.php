<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\OnboardingReviewLog;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escalating to Compliance must narrow who may act, not widen it (item 11);
 * a submitted application belongs to whoever holds it (items 4 and 12); and
 * the workload must show where an application stands now, not every verdict it
 * ever had (item 13).
 */
class EscalationAndReviewLockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(AdminRole $role, string $email): Admin
    {
        return Admin::create([
            'name' => ucfirst($role->value), 'email' => $email, 'password' => 'x',
            'is_active' => true, 'role' => $role,
        ]);
    }

    private function onboarding(array $attributes = []): UserOnboarding
    {
        $user = User::create([
            'email' => 'c'.uniqid().'@test.com', 'name' => 'Client', 'position' => 'CFO',
        ]);

        return UserOnboarding::create(array_merge([
            'user_id' => $user->id, 'reference' => 'REF-'.uniqid(),
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
        ], $attributes));
    }

    private function service(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    public function test_an_escalated_application_is_closed_to_a_manager(): void
    {
        $onboarding = $this->onboarding(['approval_state' => 'escalated']);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertFalse($this->service()->canDecide($onboarding, $manager));
    }

    public function test_an_escalated_application_is_closed_to_an_admin(): void
    {
        $onboarding = $this->onboarding(['approval_state' => 'escalated']);
        $admin = $this->admin(AdminRole::Admin, 'admin@test.com');

        $this->assertFalse($this->service()->canDecide($onboarding, $admin));
    }

    public function test_an_unassigned_escalated_application_is_still_closed(): void
    {
        // The assignment short-circuit ran before the escalation check, so an
        // unassigned escalated application was decidable by anyone.
        $onboarding = $this->onboarding(['approval_state' => 'escalated', 'assigned_to' => null]);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertFalse($this->service()->canDecide($onboarding, $manager));
    }

    public function test_compliance_can_decide_an_escalated_application(): void
    {
        $onboarding = $this->onboarding(['approval_state' => 'escalated']);
        $compliance = $this->admin(AdminRole::Compliance, 'compliance@test.com');

        $this->assertTrue($this->service()->canDecide($onboarding, $compliance));
    }

    public function test_super_admin_keeps_break_glass_access(): void
    {
        $onboarding = $this->onboarding(['approval_state' => 'escalated']);
        $super = $this->admin(AdminRole::SuperAdmin, 'super@test.com');

        $this->assertTrue($this->service()->canDecide($onboarding, $super));
    }

    public function test_a_four_eyes_handoff_is_still_open_to_a_checker(): void
    {
        // pending_approval must keep its existing behaviour — only escalation
        // narrows access.
        $holder = $this->admin(AdminRole::Analyst, 'analyst@test.com');
        $onboarding = $this->onboarding([
            'approval_state' => 'pending_approval', 'assigned_to' => $holder->id,
        ]);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertTrue($this->service()->canDecide($onboarding, $manager));
    }

    public function test_an_escalated_application_leaves_a_managers_approval_queue(): void
    {
        $this->onboarding(['approval_state' => 'escalated']);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertSame(0, UserOnboarding::awaitingApprovalBy($manager)->count());
    }

    public function test_it_appears_in_the_compliance_queue(): void
    {
        $this->onboarding(['approval_state' => 'escalated']);
        $compliance = $this->admin(AdminRole::Compliance, 'compliance@test.com');

        $this->assertSame(1, UserOnboarding::awaitingApprovalBy($compliance)->count());
    }

    public function test_review_cannot_start_before_the_client_submits(): void
    {
        $draft = $this->onboarding(['status' => 'in_progress', 'completed_at' => null]);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertFalse($draft->isReviewableBy($manager));
    }

    public function test_a_second_reviewer_cannot_work_a_held_application(): void
    {
        $holder = $this->admin(AdminRole::Analyst, 'holder@test.com');
        $other = $this->admin(AdminRole::Manager, 'other@test.com');
        $onboarding = $this->onboarding(['assigned_to' => $holder->id]);

        $this->assertTrue($onboarding->isReviewableBy($holder), 'the holder may review');
        $this->assertFalse($onboarding->isReviewableBy($other), 'a second reviewer may not');
    }

    public function test_unassigned_work_stays_open(): void
    {
        $onboarding = $this->onboarding(['assigned_to' => null]);
        $manager = $this->admin(AdminRole::Manager, 'manager@test.com');

        $this->assertTrue($onboarding->isReviewableBy($manager));
    }

    public function test_an_admin_can_step_in_on_someone_elses_application(): void
    {
        $holder = $this->admin(AdminRole::Analyst, 'holder@test.com');
        $onboarding = $this->onboarding(['assigned_to' => $holder->id]);

        $this->assertTrue($onboarding->isReviewableBy($this->admin(AdminRole::Admin, 'admin@test.com')));
    }

    public function test_a_rejected_then_approved_application_counts_once(): void
    {
        // The review log is append-only, so the same application appeared under
        // both Approved and Rejected in the team workload (item 13).
        $reviewer = $this->admin(AdminRole::Manager, 'reviewer@test.com');
        $onboarding = $this->onboarding(['status' => 'approved']);

        OnboardingReviewLog::create([
            'user_onboarding_id' => $onboarding->id, 'admin_id' => $reviewer->id,
            'event' => 'rejected', 'created_at' => now()->subDays(3),
        ]);
        OnboardingReviewLog::create([
            'user_onboarding_id' => $onboarding->id, 'admin_id' => $reviewer->id,
            'event' => 'approved', 'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin(AdminRole::SuperAdmin, 'super@test.com'), 'admin')
            ->get(route('admin.dashboard'))->assertOk();

        $workload = collect(view()->shared('workload') ?? []);

        // Assert through the same reduction the dashboard uses.
        $latest = OnboardingReviewLog::whereIn('event', ['approved', 'rejected'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['admin_id', 'event', 'user_onboarding_id'])
            ->unique('user_onboarding_id')
            ->groupBy('admin_id')
            ->map(fn ($rows) => $rows->countBy('event'));

        $own = $latest->get($reviewer->id, collect());
        $this->assertSame(1, (int) ($own['approved'] ?? 0), 'counted once, as approved');
        $this->assertSame(0, (int) ($own['rejected'] ?? 0), 'and not also as rejected');
    }
}
