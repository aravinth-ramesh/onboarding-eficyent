<?php

namespace Tests\Feature;

use App\Enums\Ability;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private UserType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = UserType::create(['name' => 'Corporate', 'slug' => 'corporate', 'order' => 1, 'is_active' => true]);
    }

    private function admin(AdminRole $role, string $email): Admin
    {
        return Admin::create(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'x', 'is_active' => true, 'role' => $role]);
    }

    private function onboarding(string $email, ?Admin $assignee = null, string $status = 'completed'): UserOnboarding
    {
        $user = User::create(['email' => $email, 'name' => 'Co', 'position' => 'CFO']);

        return UserOnboarding::create([
            'user_id' => $user->id, 'user_type_id' => $this->type->id,
            'status' => $status, 'started_at' => now(), 'assigned_to' => $assignee?->id,
        ]);
    }

    // --- Ability matrix ------------------------------------------------------

    public function test_the_role_ability_matrix_is_wired_as_designed(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $compliance = $this->admin(AdminRole::Compliance, 'c@t.com');
        $super = $this->admin(AdminRole::SuperAdmin, 's@t.com');

        // Analyst: front-line only.
        $this->assertTrue($analyst->hasAbility(Ability::REVIEW_ONBOARDING));
        $this->assertFalse($analyst->hasAbility(Ability::APPROVE_ONBOARDING));
        $this->assertFalse($analyst->hasAbility(Ability::ASSIGN_ONBOARDING));
        $this->assertFalse($analyst->hasAbility(Ability::MANAGE_TEMPLATES));
        $this->assertTrue($analyst->seesOnlyAssignedOnboardings());

        // Manager: approves + assigns, sees all, but no platform config.
        $this->assertTrue($manager->hasAbility(Ability::APPROVE_ONBOARDING));
        $this->assertTrue($manager->hasAbility(Ability::ASSIGN_ONBOARDING));
        $this->assertFalse($manager->hasAbility(Ability::MANAGE_TEMPLATES));
        $this->assertFalse($manager->seesOnlyAssignedOnboardings());

        // Compliance: decides + tunes doc policy + audit, but doesn't assign.
        $this->assertTrue($compliance->hasAbility(Ability::APPROVE_ONBOARDING));
        $this->assertTrue($compliance->hasAbility(Ability::TUNE_DOCUMENT_POLICY));
        $this->assertTrue($compliance->hasAbility(Ability::VIEW_ACTIVITY_LOG));
        $this->assertFalse($compliance->hasAbility(Ability::ASSIGN_ONBOARDING));

        // Super admin: the full set.
        $this->assertSame(Ability::all(), $super->role->abilities());
        $this->assertTrue($super->hasAbility(Ability::MANAGE_USERS));
    }

    // --- List & record scoping ----------------------------------------------

    public function test_analyst_sees_only_their_assigned_companies_in_the_list(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $mine = $this->onboarding('mine@co.com', $analyst);
        $notMine = $this->onboarding('other@co.com', null);

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertOk()
            ->assertSee('mine@co.com')
            ->assertDontSee('other@co.com');
    }

    public function test_manager_sees_every_company(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');
        $this->onboarding('one@co.com');
        $this->onboarding('two@co.com');

        $this->actingAs($manager, 'admin')
            ->get(route('admin.user-onboardings.index'))
            ->assertSee('one@co.com')
            ->assertSee('two@co.com');
    }

    public function test_analyst_cannot_open_a_company_not_assigned_to_them(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $mine = $this->onboarding('mine@co.com', $analyst);
        $notMine = $this->onboarding('other@co.com', null);

        $this->actingAs($analyst, 'admin')->get(route('admin.user-onboardings.show', $mine))->assertOk();
        $this->actingAs($analyst, 'admin')->get(route('admin.user-onboardings.show', $notMine))->assertForbidden();
    }

    public function test_analyst_cannot_reach_any_surface_of_a_company_not_assigned_to_them(): void
    {
        // Every per-application admin surface must honour visibility, not just
        // show/decisions — exportPdf and answerHistory in particular exposed
        // the full application to any admin (EOP-92).
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $notMine = $this->onboarding('other@co.com', null);

        $group = \App\Models\QuestionGroup::create(['name' => 'G', 'slug' => 'g', 'order' => 1, 'is_active' => true]);
        $question = \App\Models\Question::create(['question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = \App\Models\UserAnswer::create(['user_id' => $notMine->user_id, 'question_id' => $question->id, 'user_onboarding_id' => $notMine->id, 'value' => 'Secret Ltd']);

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.user-onboardings.export-pdf', $notMine))->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->get(route('admin.user-onboardings.answers.history', [$notMine, $answer]))->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.messages.reply', $notMine), ['body' => 'hi'])->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.answers.request-change', [$notMine, $answer]), ['message' => 'fix'])->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->get(route('admin.user-onboardings.new-question', $notMine))->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.archive', $notMine))->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.unarchive', $notMine))->assertForbidden();
        $this->actingAs($analyst, 'admin')
            ->post(route('admin.user-onboardings.notes.store', $notMine), ['note' => 'x'])->assertForbidden();
    }

    // --- Route ability gating -----------------------------------------------

    public function test_analyst_is_blocked_from_configuration_and_decisions(): void
    {
        $analyst = $this->admin(AdminRole::Analyst, 'a@t.com');
        $company = $this->onboarding('x@co.com', $analyst);

        // Configuration & monitoring — 403.
        $this->actingAs($analyst, 'admin')->get(route('admin.user-types.index'))->assertForbidden();
        $this->actingAs($analyst, 'admin')->get(route('admin.email-templates.index'))->assertForbidden();
        $this->actingAs($analyst, 'admin')->get(route('admin.scheduled-emails.index'))->assertForbidden();
        $this->actingAs($analyst, 'admin')->get(route('admin.document-reviews.index'))->assertForbidden();
        $this->actingAs($analyst, 'admin')->get(route('admin.admin-activity.index'))->assertForbidden();

        // Decisions & assignment — 403 even for their own company.
        $this->actingAs($analyst, 'admin')->post(route('admin.user-onboardings.approve', $company))->assertForbidden();
        $this->actingAs($analyst, 'admin')->post(route('admin.user-onboardings.reject', $company))->assertForbidden();
        $this->actingAs($analyst, 'admin')->post(route('admin.user-onboardings.assign', $company))->assertForbidden();
    }

    public function test_manager_can_reach_decisions_but_not_platform_config(): void
    {
        $manager = $this->admin(AdminRole::Manager, 'm@t.com');

        // Broadcast emails and workload are a manager's; template config is not.
        $this->actingAs($manager, 'admin')->get(route('admin.scheduled-emails.index'))->assertOk();
        $this->actingAs($manager, 'admin')->get(route('admin.user-types.index'))->assertForbidden();
    }

    public function test_compliance_reaches_document_review_and_audit_but_not_templates(): void
    {
        $compliance = $this->admin(AdminRole::Compliance, 'c@t.com');

        $this->actingAs($compliance, 'admin')->get(route('admin.document-reviews.index'))->assertOk();
        $this->actingAs($compliance, 'admin')->get(route('admin.admin-activity.index'))->assertOk();
        $this->actingAs($compliance, 'admin')->get(route('admin.user-types.index'))->assertForbidden();
    }

    public function test_admin_reaches_configuration(): void
    {
        $admin = $this->admin(AdminRole::Admin, 'd@t.com');

        $this->actingAs($admin, 'admin')->get(route('admin.user-types.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.email-templates.index'))->assertOk();
    }
}
