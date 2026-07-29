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

class AssignmentRolesTest extends TestCase
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

    private function company(string $email = 'co@t.com', ?Admin $assignee = null): UserOnboarding
    {
        $user = User::create(['email' => $email, 'name' => 'Co', 'position' => 'CFO']);

        return UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => 'completed', 'started_at' => now(), 'assigned_to' => $assignee?->id,
        ]);
    }

    // --- single assign target must be a reviewer -----------------------------

    public function test_a_company_can_be_assigned_to_an_analyst_or_manager(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $company = $this->company();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.assign', $company), ['assigned_to' => $analyst->id])
            ->assertRedirect(route('admin.user-onboardings.show', $company));

        $this->assertSame($analyst->id, $company->refresh()->assigned_to);
    }

    public function test_a_company_cannot_be_assigned_to_a_non_reviewer(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $compliance = $this->admin(AdminRole::Compliance, 'c@t.com');
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $company = $this->company();

        foreach ([$compliance, $super] as $target) {
            $this->actingAs($manager, 'admin')
                ->post(route('admin.user-onboardings.assign', $company), ['assigned_to' => $target->id])
                ->assertSessionHasErrors('assigned_to');
        }

        $this->assertNull($company->refresh()->assigned_to);
    }

    // --- auto-assign targets the analyst pool --------------------------------

    public function test_auto_assign_prefers_the_least_loaded_analyst_over_other_roles(): void
    {
        config(['onboarding.auto_assign_submissions' => true]);

        $manager = $this->admin(AdminRole::Manager, 'm@t.com');   // reviewer, but not the auto pool
        $busy = $this->admin(AdminRole::Analyst, 'busy@t.com');
        $free = $this->admin(AdminRole::Analyst, 'free@t.com');
        // Give "busy" an open assignment so "free" is least-loaded.
        $this->company('x@t.com', $busy);

        $service = app(\App\Services\OnboardingService::class);
        $reflection = new \ReflectionMethod($service, 'autoAssign');
        $reflection->setAccessible(true);
        $fresh = $this->company('new@t.com');
        $reflection->invoke($service, $fresh);

        $this->assertSame($free->id, $fresh->refresh()->assigned_to);
    }

    // --- bulk assign ---------------------------------------------------------

    public function test_manager_bulk_assigns_several_companies_to_an_analyst(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $a = $this->company('a@t.com');
        $b = $this->company('b@t.com');
        $c = $this->company('c@t.com');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.bulk-assign'), ['ids' => [$a->id, $b->id], 'assigned_to' => $analyst->id])
            ->assertRedirect(route('admin.user-onboardings.index'))
            ->assertSessionHas('success');

        $this->assertSame($analyst->id, $a->refresh()->assigned_to);
        $this->assertSame($analyst->id, $b->refresh()->assigned_to);
        $this->assertNull($c->refresh()->assigned_to); // not selected
    }

    public function test_bulk_assign_rejects_a_non_reviewer_target(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');
        $a = $this->company('a@t.com');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.user-onboardings.bulk-assign'), ['ids' => [$a->id], 'assigned_to' => $super->id])
            ->assertSessionHasErrors('assigned_to');

        $this->assertNull($a->refresh()->assigned_to);
    }

    public function test_an_analyst_cannot_bulk_assign(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $other = $this->admin(AdminRole::Analyst, 'o@t.com');
        $a = $this->company('a@t.com', $analyst);

        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.bulk-assign'), ['ids' => [$a->id], 'assigned_to' => $other->id])
            ->assertForbidden();
    }

    // --- role-based landing --------------------------------------------------

    public function test_an_analyst_is_redirected_from_the_dashboard_to_their_queue(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.user-onboardings.index'));
    }

    public function test_a_manager_still_sees_the_dashboard_with_workload(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');

        $this->actingAs($manager, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Team Workload');
    }

    public function test_an_analyst_sees_no_bulk_controls_on_the_list(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $this->company('mine@t.com', $analyst);

        // No select-all checkbox, no bulk-assign form for a role with no bulk
        // ability. (Assert on the id="..." markup — the strings also appear in
        // the always-rendered <script>.)
        $this->actingAs($analyst, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertDontSee('id="bulkSelectAll"', false)
            ->assertDontSee('id="bulkAssignForm"', false)
            ->assertDontSee('id="bulkBar"', false);
    }
}
