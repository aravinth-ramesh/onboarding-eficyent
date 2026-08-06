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
 * A change request shows the parent onboarding section (question group), not
 * just the question, so the reviewer can tell which part needs updating
 * (bug report EOP-75).
 */
class ChangeRequestSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_request_row_shows_the_parent_section(): void
    {
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $user = User::create(['email' => 'c@t.com', 'name' => 'Co', 'position' => 'CFO']);
        $onb = UserOnboarding::create(['user_id' => $user->id, 'user_type_id' => $type->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $group = QuestionGroup::create(['name' => 'Ultimate Beneficial Owners', 'slug' => 'ubo', 'order' => 1, 'is_active' => true]);
        $q = Question::create(['question_group_id' => $group->id, 'label' => 'Ownership percentage', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
        $answer = UserAnswer::create(['user_id' => $user->id, 'question_id' => $q->id, 'user_onboarding_id' => $onb->id, 'value' => '10']);
        $admin = Admin::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::SuperAdmin]);

        AdminNotification::create([
            'user_id' => $user->id, 'admin_id' => $admin->id, 'type' => 'change_request',
            'user_answer_id' => $answer->id, 'message' => 'Please confirm the percentage.', 'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.user-onboardings.show', $onb))
            ->assertOk()
            // The section name and the section-context marker both appear.
            ->assertSee('Ultimate Beneficial Owners')
            ->assertSee('bi-folder2', false);
    }
}
