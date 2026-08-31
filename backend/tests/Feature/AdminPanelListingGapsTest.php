<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AnswerFile;
use App\Models\ConditionalRule;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two admin listings that rendered as empty: conditional rules whose questions
 * had been deleted showed rows with no detail (item 1), and the document queue
 * hid every upload the automation never assessed (item 15).
 */
class AdminPanelListingGapsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Ops', 'email' => 'ops@test.com', 'password' => 'x',
            'is_active' => true, 'role' => AdminRole::SuperAdmin,
        ]);
    }

    private function question(string $label): Question
    {
        $group = QuestionGroup::create(['name' => 'G', 'slug' => 'g-'.uniqid(), 'order' => 1, 'is_active' => true]);

        return Question::create([
            'question_group_id' => $group->id, 'label' => $label, 'type' => 'text',
            'is_required' => false, 'order' => 1, 'is_active' => true,
        ]);
    }

    public function test_a_rule_whose_question_was_deleted_still_names_it(): void
    {
        $target = $this->question('Trading name');
        $parent = $this->question('Do you trade under another name?');

        ConditionalRule::create([
            'question_id' => $target->id, 'parent_question_id' => $parent->id,
            'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);

        // Soft-delete straight on the DB so the rule survives, reproducing rows
        // created before questions cleaned up after themselves.
        Question::whereIn('id', [$target->id, $parent->id])->update(['deleted_at' => now()]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.conditional-rules.index'))
            ->assertOk()
            ->assertSee('Trading name')
            ->assertSee('Do you trade under another name?')
            ->assertSee('deleted');
    }

    public function test_deleting_a_question_takes_its_rules_with_it(): void
    {
        $target = $this->question('Trading name');
        $parent = $this->question('Trades under another name?');

        $rule = ConditionalRule::create([
            'question_id' => $target->id, 'parent_question_id' => $parent->id,
            'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);

        $target->delete();

        $this->assertDatabaseMissing('conditional_rules', ['id' => $rule->id]);
    }

    public function test_a_rule_depending_on_a_deleted_parent_is_removed_too(): void
    {
        $target = $this->question('Trading name');
        $parent = $this->question('Trades under another name?');

        $rule = ConditionalRule::create([
            'question_id' => $target->id, 'parent_question_id' => $parent->id,
            'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show',
            'logical_operator' => 'and', 'is_active' => true,
        ]);

        $parent->delete();

        $this->assertDatabaseMissing('conditional_rules', ['id' => $rule->id]);
    }

    public function test_a_document_the_automation_never_assessed_awaits_review(): void
    {
        // Validation short-circuits to `skipped` when the question carries no
        // expected_document policy, and nothing seeds that policy — so on a real
        // install every upload was skipped and the queue looked empty (item 15).
        $user = User::create(['email' => 'client@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-D', 'status' => 'submitted', 'started_at' => now(),
        ]);
        $question = $this->question('Certificate of Incorporation');
        $answer = UserAnswer::create([
            'user_onboarding_id' => $onboarding->id, 'user_id' => $user->id,
            'question_id' => $question->id, 'value' => json_encode(['uploads/cert.pdf']),
        ]);
        AnswerFile::create([
            'user_answer_id' => $answer->id, 'original_filename' => 'certificate.pdf',
            's3_path' => 'uploads/cert.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'disk' => 'local', 'validation_status' => 'skipped',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.document-reviews.index'))
            ->assertOk()
            ->assertSee('certificate.pdf')
            ->assertDontSee('Nothing awaiting review');
    }

    public function test_an_already_passed_document_stays_out_of_the_queue(): void
    {
        $user = User::create(['email' => 'client2@test.com', 'name' => 'Client', 'position' => 'CFO']);
        $onboarding = UserOnboarding::create([
            'user_id' => $user->id, 'reference' => 'REF-P', 'status' => 'submitted', 'started_at' => now(),
        ]);
        $answer = UserAnswer::create([
            'user_onboarding_id' => $onboarding->id, 'user_id' => $user->id,
            'question_id' => $this->question('Licence')->id, 'value' => json_encode(['uploads/ok.pdf']),
        ]);
        AnswerFile::create([
            'user_answer_id' => $answer->id, 'original_filename' => 'passed-licence.pdf',
            's3_path' => 'uploads/ok.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'disk' => 'local', 'validation_status' => 'passed',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.document-reviews.index'))
            ->assertOk()
            ->assertDontSee('passed-licence.pdf');
    }
}
