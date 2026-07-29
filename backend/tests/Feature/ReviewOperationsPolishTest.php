<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 5 polish — SLA/aging, the four-eyes checker work-queue, and the
 * reassignment audit trail.
 */
class ReviewOperationsPolishTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
    }

    private function admin(AdminRole $role, string $email): Admin
    {
        return Admin::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'x', 'is_active' => true, 'role' => $role]);
    }

    private function company(string $email = 'co@t.com', array $attrs = []): UserOnboarding
    {
        $user = User::create(['email' => $email, 'name' => 'Co', 'position' => 'CFO']);

        return UserOnboarding::create(array_merge([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now(),
        ], $attrs));
    }

    // --- SLA / aging ---------------------------------------------------------

    public function test_aging_flags_a_review_as_overdue_past_the_threshold(): void
    {
        config(['onboarding.sla.review_days' => 3]);

        $fresh = $this->company('fresh@t.com', ['completed_at' => now()->subDay()]);
        $stale = $this->company('stale@t.com', ['completed_at' => now()->subDays(5)]);

        $this->assertFalse($fresh->reviewAging()['overdue']);
        $this->assertTrue($stale->reviewAging()['overdue']);
        $this->assertSame('review', $stale->reviewAging()['stage']);
    }

    public function test_pending_approval_ages_against_the_approval_sla(): void
    {
        config(['onboarding.sla.approval_days' => 2]);

        $onb = $this->company('a@t.com', [
            'approval_state' => 'pending_approval',
            'submitted_for_approval_at' => now()->subDays(4),
        ]);

        $aging = $onb->reviewAging();
        $this->assertSame('approval', $aging['stage']);
        $this->assertTrue($aging['overdue']);
    }

    public function test_decided_applications_do_not_age(): void
    {
        $approved = $this->company('done@t.com', ['status' => 'approved', 'completed_at' => now()->subDays(10)]);
        $this->assertNull($approved->reviewAging());
    }

    // --- four-eyes checker queue --------------------------------------------

    public function test_approval_queue_excludes_the_admins_own_submissions(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $other = $this->admin(AdminRole::Manager, 'o@t.com');

        $mine = $this->company('mine@t.com', ['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $manager->id]);
        $theirs = $this->company('theirs@t.com', ['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $other->id]);

        $queue = UserOnboarding::awaitingApprovalBy($manager)->pluck('id');

        $this->assertTrue($queue->contains($theirs->id));
        $this->assertFalse($queue->contains($mine->id));
    }

    public function test_dashboard_shows_the_awaiting_approval_card_to_a_checker(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $other = $this->admin(AdminRole::Manager, 'o@t.com');
        $this->company('x@t.com', ['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $other->id]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Awaiting your approval');
    }

    public function test_onboardings_nav_badge_counts_awaiting_approval(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $other = $this->admin(AdminRole::Manager, 'o@t.com');
        $this->company('x@t.com', ['approval_state' => 'pending_approval', 'submitted_for_approval_by' => $other->id]);

        // The sidebar badge title carries the count.
        $this->actingAs($manager, 'admin')
            ->get(route('admin.dashboard'))
            ->assertSee('1 awaiting your approval');
    }

    // --- reassignment audit --------------------------------------------------

    public function test_assigning_writes_a_reassignment_event(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $company = $this->company();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.assign', $company), ['assigned_to' => $analyst->id])
            ->assertRedirect();

        $this->assertDatabaseHas('onboarding_review_logs', [
            'user_onboarding_id' => $company->id, 'event' => 'assigned', 'admin_id' => $manager->id,
        ]);
    }

    public function test_reassignment_comment_names_both_reviewers(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $first = $this->admin(AdminRole::Analyst, 'first@t.com');
        $second = $this->admin(AdminRole::Analyst, 'second@t.com');
        $company = $this->company('c@t.com', ['assigned_to' => $first->id]);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.assign', $company), ['assigned_to' => $second->id]);

        $log = $company->reviewLogs()->where('event', 'assigned')->latest('id')->first();
        $this->assertStringContainsString('Reassigned from', $log->comment);
        $this->assertStringContainsString($first->name, $log->comment);
        $this->assertStringContainsString($second->name, $log->comment);
    }

    // --- CSV completeness ----------------------------------------------------

    public function test_csv_export_includes_the_review_ops_columns(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $maker = $this->admin(AdminRole::Analyst, 'maker@t.com');
        $this->company('c@t.com', [
            'approval_state' => 'pending_approval',
            'submitted_for_approval_by' => $maker->id,
            'submitted_for_approval_at' => now()->subDays(3),
            'completed_at' => now()->subDays(5),
        ]);

        $csv = $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.export-csv'))
            ->streamedContent();

        $this->assertStringContainsString('Approval Stage', $csv);
        $this->assertStringContainsString('Submitted For Approval By', $csv);
        $this->assertStringContainsString('Days Waiting', $csv);
        $this->assertStringContainsString('Awaiting approval', $csv);
        $this->assertStringContainsString($maker->name, $csv);
    }

    public function test_bulk_assign_logs_each_moved_application(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $a = $this->company('a@t.com');
        $b = $this->company('b@t.com');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.bulk-assign'), ['ids' => [$a->id, $b->id], 'assigned_to' => $analyst->id])
            ->assertRedirect();

        $this->assertDatabaseHas('onboarding_review_logs', ['user_onboarding_id' => $a->id, 'event' => 'assigned']);
        $this->assertDatabaseHas('onboarding_review_logs', ['user_onboarding_id' => $b->id, 'event' => 'assigned']);
    }
}
