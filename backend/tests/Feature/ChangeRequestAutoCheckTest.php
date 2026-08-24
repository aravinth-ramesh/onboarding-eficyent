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
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A resolved change request is auto-marked "checked" when the admin reviews it
 * — via the answer's edit history or by marking the section reviewed — so no
 * separate "Checked" click is needed (bug report EOP-76).
 */
class ChangeRequestAutoCheckTest extends TestCase
{
    use RefreshDatabase;

    private UserOnboarding $onb;

    private QuestionGroup $group;

    private UserAnswer $answer;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $this->onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $this->group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $this->group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $this->answer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $this->onb->id, 'value' => 'Acme']);
        $this->admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Manager]);
    }

    private function resolvedChangeRequest(): AdminNotification
    {
        return AdminNotification::create([
            'user_id' => $this->onb->user_id, 'admin_id' => $this->admin->id, 'type' => 'change_request',
            'user_answer_id' => $this->answer->id, 'message' => 'fix', 'status' => 'resolved', 'resolved_at' => now(),
        ]);
    }

    public function test_opening_the_answer_history_auto_checks_a_resolved_change(): void
    {
        $notification = $this->resolvedChangeRequest();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.answers.history', [$this->onb, $this->answer]))
            ->assertOk();

        $this->assertNotNull($notification->refresh()->checked_at);
        $this->assertSame($this->admin->id, $notification->checked_by);
    }

    public function test_marking_the_section_reviewed_auto_checks_resolved_changes(): void
    {
        $notification = $this->resolvedChangeRequest();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.user-onboardings.sections.review', [$this->onb, $this->group]), ['status' => 'completed'])
            ->assertSessionHas('success');

        $this->assertNotNull($notification->refresh()->checked_at);
    }

    public function test_a_still_pending_change_is_not_auto_checked(): void
    {
        $pending = AdminNotification::create([
            'user_id' => $this->onb->user_id, 'admin_id' => $this->admin->id, 'type' => 'change_request',
            'user_answer_id' => $this->answer->id, 'message' => 'fix', 'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.user-onboardings.answers.history', [$this->onb, $this->answer]))
            ->assertOk();

        $this->assertNull($pending->refresh()->checked_at);
    }
}
