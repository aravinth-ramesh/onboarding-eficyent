<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AnswerAuditLog;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use App\Models\UserType;
use App\Services\AnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client answer changes are audit-logged only AFTER the application has been
 * submitted for review — draft edits are the client still filling the form and
 * aren't the onboarding team's concern.
 */
class ClientChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $type = UserType::create(['name' => 'Corp', 'slug' => 'corp', 'order' => 1, 'is_active' => true]);
        $this->type = $type;
        $this->client = User::create(['email' => 'client@t.com', 'name' => 'Acme', 'position' => 'CFO']);
        $group = QuestionGroup::create(['name' => 'Company', 'slug' => 'company', 'order' => 1, 'is_active' => true]);
        $this->question = Question::create(['question_group_id' => $group->id, 'label' => 'Legal name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'is_active' => true]);
    }

    private function onboarding(array $attrs = []): UserOnboarding
    {
        return UserOnboarding::create(array_merge([
            'user_id' => $this->client->id, 'user_type_id' => $this->type->id,
            'status' => 'in_progress', 'started_at' => now(),
        ], $attrs));
    }

    private function seedAnswer(UserOnboarding $onb, string $value = 'Old Name'): UserAnswer
    {
        return UserAnswer::create([
            'user_id' => $this->client->id, 'question_id' => $this->question->id,
            'user_onboarding_id' => $onb->id, 'value' => $value,
        ]);
    }

    private function edit(UserOnboarding $onb, string $newValue): void
    {
        app(AnswerService::class)->saveAnswer($this->client, $onb, $this->question->id, $newValue);
    }

    public function test_a_draft_edit_before_submission_is_not_logged(): void
    {
        $onb = $this->onboarding(['status' => 'in_progress']); // never submitted
        $this->seedAnswer($onb);

        $this->edit($onb, 'New Name');

        $this->assertSame('New Name', $onb->answers()->first()->value, 'the value must still update');
        $this->assertSame(0, AnswerAuditLog::count(), 'draft edits must not be logged');
    }

    public function test_an_edit_after_submission_is_logged(): void
    {
        $onb = $this->onboarding(['status' => 'completed', 'completed_at' => now()]);
        $this->seedAnswer($onb);

        $this->edit($onb, 'Corrected Name');

        $this->assertSame(1, AnswerAuditLog::count());
        $log = AnswerAuditLog::first();
        $this->assertSame('Old Name', $log->old_value);
        $this->assertSame('Corrected Name', $log->new_value);
    }

    public function test_a_resubmission_edit_is_logged_even_while_in_progress(): void
    {
        // Rejected → reopened for resubmission: status is in_progress again, but
        // reopened_at marks it as already-reviewed, so edits are logged.
        $onb = $this->onboarding(['status' => 'in_progress', 'reopened_at' => now()]);
        $this->seedAnswer($onb);

        $this->edit($onb, 'Fixed Name');

        $this->assertSame(1, AnswerAuditLog::count());
    }

    public function test_an_approved_application_still_logs_late_changes(): void
    {
        $onb = $this->onboarding(['status' => 'approved', 'completed_at' => now()]);
        $this->seedAnswer($onb);

        $this->edit($onb, 'Changed After Approval');

        $this->assertSame(1, AnswerAuditLog::count());
    }

    public function test_the_client_changes_page_shows_the_application_and_is_gated(): void
    {
        $onb = $this->onboarding(['status' => 'completed', 'completed_at' => now()]);
        $this->seedAnswer($onb);
        $this->edit($onb, 'Corrected');

        // Compliance can view the activity log; an analyst cannot.
        $analyst = Admin::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Analyst]);
        $compliance = Admin::create(['name' => 'C', 'email' => 'c@t.com', 'password' => 'x', 'is_active' => true, 'role' => AdminRole::Compliance]);

        $this->actingAs($analyst, 'admin')->get(route('admin.audit-logs.index'))->assertForbidden();

        $this->actingAs($compliance, 'admin')
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee($onb->reference)
            ->assertSee('Corrected');
    }

    private UserType $type;
}
